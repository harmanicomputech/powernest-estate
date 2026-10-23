<?php

namespace App\Http\Controllers\Power;

use App\Enums\PowerPurchaseStatus;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationException;
use App\Integrations\Payments\GatewayManager;
use App\Models\Meter;
use App\Models\PowerPurchase;
use App\Models\SupportTicket;
use App\Notifications\PowerTokenIssued;
use App\Services\Power\PowerPurchaseService;
use App\Support\EstateContext;
use App\Support\Money;
use App\Support\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    public function __construct(private EstateContext $context, private PowerPurchaseService $service) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $purchases = PowerPurchase::forEstate($estate)->where('user_id', $request->user()->id)->with('token', 'meter')
            ->when($status === 'success', fn ($q) => $q->where('status', PowerPurchaseStatus::TokenIssued->value))
            ->when($status === 'pending', fn ($q) => $q->whereIn('status', [PowerPurchaseStatus::PendingPayment->value, PowerPurchaseStatus::Paid->value, PowerPurchaseStatus::Vending->value]))
            ->when($status === 'issues', fn ($q) => $q->whereIn('status', [PowerPurchaseStatus::PaymentFailed->value, PowerPurchaseStatus::VendFailed->value, PowerPurchaseStatus::ReconciliationRequired->value, PowerPurchaseStatus::Expired->value]))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$search}%")->orWhere('meter_number', 'like', "%{$search}%")))
            ->latest()->paginate(15)->withQueryString();

        return view('power.history', compact('purchases', 'status', 'search'));
    }

    public function create(Request $request, GatewayManager $gateways): View
    {
        $estate = $this->context->estate();
        $propertyIds = $request->user()->authorizedProperties($estate)->pluck('properties.id');

        return view('power.buy', [
            'meters' => Meter::forEstate($estate)->whereIn('property_id', $propertyIds)->where('status', 'active')->with('property')->get(),
            'selected' => (int) $request->query('meter'),
            'gateways' => $gateways->availableFor($estate),
            'serviceCharge' => (int) $estate->setting('power.service_charge'),
            'min' => (int) $estate->setting('power.min_amount'),
            'max' => (int) $estate->setting('power.max_amount'),
            'properties' => $request->user()->authorizedProperties($estate)->orderBy('identifier')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'meter_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:15'],
            'gateway' => ['required', 'string', 'in:payaza,paystack'],
        ]);

        try {
            $amount = Money::parse($data['amount']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter a valid amount in naira.']);
        }

        $estate = $this->context->estate();
        $meter = Meter::forEstate($estate)->findOrFail($data['meter_id']);
        $purchase = $this->service->initiate($request->user(), $estate, $meter, $amount, $data['gateway']);

        return redirect()->route('power.purchases.pay', $purchase);
    }

    public function pay(PowerPurchase $purchase): View|RedirectResponse
    {
        $this->authorizeOwner($purchase);

        if ($purchase->status !== PowerPurchaseStatus::PendingPayment) {
            return redirect()->route('power.purchases.show', $purchase);
        }

        try {
            $session = $this->service->checkout($purchase);
        } catch (IntegrationException $e) {
            Log::warning('Checkout unavailable', ['reference' => $purchase->reference, 'error' => $e->getMessage()]);

            return redirect()->route('power.purchases.show', $purchase)->with('error', 'The payment provider is unavailable right now. Nothing was charged; please try again shortly.');
        }

        if ($session->mode === 'redirect') {
            return redirect()->away($session->redirectUrl);
        }

        return view('power.pay', ['purchase' => $purchase, 'session' => $session]);
    }

    /**
     * Browser return from the gateway. The query string is ignored; we ask the gateway ourselves.
     */
    public function return(PowerPurchase $purchase): RedirectResponse
    {
        $this->authorizeOwner($purchase);
        $this->service->verifyWithGateway($purchase, 'callback');

        return redirect()->route('power.purchases.show', $purchase);
    }

    /**
     * Called after the inline checkout reports completion. Only triggers a server-side verification.
     */
    public function verify(PowerPurchase $purchase, Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($purchase);
        $purchase = $this->service->verifyWithGateway($purchase, 'callback');

        return $request->expectsJson()
            ? response()->json(['status' => $purchase->status->value, 'redirect' => route('power.purchases.show', $purchase)])
            : redirect()->route('power.purchases.show', $purchase);
    }

    public function show(PowerPurchase $purchase): View
    {
        Gate::authorize('view', $purchase);

        return view('power.show', ['purchase' => $purchase->load('token', 'meter.property', 'events')]);
    }

    public function resend(PowerPurchase $purchase, Request $request): RedirectResponse
    {
        $this->authorizeOwner($purchase);
        abort_unless($purchase->status === PowerPurchaseStatus::TokenIssued && $purchase->token, 422);

        $request->user()->notify(new PowerTokenIssued($purchase));

        return back()->with('success', 'Token sent to '.$request->user()->email.'.');
    }

    public function support(PowerPurchase $purchase, Request $request): RedirectResponse
    {
        $this->authorizeOwner($purchase);
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);

        $ticket = SupportTicket::firstOrCreate(
            ['power_purchase_id' => $purchase->id, 'status' => 'open'],
            ['estate_id' => $purchase->estate_id, 'reference' => Reference::make('ST', 7), 'user_id' => $request->user()->id, 'category' => 'power', 'subject' => "Electricity purchase {$purchase->reference}"],
        );
        $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['message'], 'is_staff' => false]);

        return back()->with('success', "Support request {$ticket->reference} received. The estate office will follow up.");
    }

    private function authorizeOwner(PowerPurchase $purchase): void
    {
        abort_unless($purchase->user_id === request()->user()->id, 403);
    }
}
