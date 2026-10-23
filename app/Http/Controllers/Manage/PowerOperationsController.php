<?php

namespace App\Http\Controllers\Manage;

use App\Enums\PowerPurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\GatewayWebhookEvent;
use App\Models\PowerPurchase;
use App\Services\Dashboard\DashboardMetrics;
use App\Services\Power\PowerPurchaseService;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PowerOperationsController extends Controller
{
    public function __construct(private EstateContext $context, private PowerPurchaseService $service) {}

    public function index(Request $request, DashboardMetrics $metrics): View
    {
        $estate = $this->context->estate();
        $status = $request->query('status', 'attention');
        $search = trim((string) $request->query('q'));

        $purchases = PowerPurchase::forEstate($estate)->with('user', 'property')
            ->when($status === 'attention', fn ($q) => $q->whereIn('status', [PowerPurchaseStatus::VendFailed->value, PowerPurchaseStatus::ReconciliationRequired->value]))
            ->when($status === 'in_flight', fn ($q) => $q->whereIn('status', [PowerPurchaseStatus::Paid->value, PowerPurchaseStatus::Vending->value]))
            ->when($status === 'pending', fn ($q) => $q->where('status', PowerPurchaseStatus::PendingPayment->value))
            ->when($status === 'issued', fn ($q) => $q->where('status', PowerPurchaseStatus::TokenIssued->value))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$search}%")->orWhere('meter_number', 'like', "%{$search}%")->orWhere('gateway_reference', 'like', "%{$search}%")))
            ->latest()->paginate(30)->withQueryString();

        $counts = PowerPurchase::forEstate($estate)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $today = now($estate->timezone())->startOfDay()->utc();

        return view('manage.power.index', [
            'purchases' => $purchases,
            'status' => $status,
            'search' => $search,
            'counts' => $counts,
            'todayVolume' => (int) PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)->where('token_issued_at', '>=', $today)->sum('vend_amount'),
            'monthVolume' => (int) PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)->where('token_issued_at', '>=', now($estate->timezone())->startOfMonth()->utc())->sum('vend_amount'),
            'monthFees' => (int) PowerPurchase::forEstate($estate)->where('status', PowerPurchaseStatus::TokenIssued->value)->where('token_issued_at', '>=', now($estate->timezone())->startOfMonth()->utc())->sum('service_charge'),
            'trend' => $metrics->powerTrend($estate),
        ]);
    }

    public function show(PowerPurchase $purchase): View
    {
        return view('manage.power.show', [
            'purchase' => $purchase->load('user', 'property', 'meter', 'token.recorder', 'vendAttempts.initiator', 'events.actor'),
            'webhooks' => GatewayWebhookEvent::where('reference', $purchase->reference)->latest()->get(),
            'canReconcile' => Gate::allows('reconcile', $purchase),
        ]);
    }

    public function reverify(PowerPurchase $purchase): RedirectResponse
    {
        Gate::authorize('reconcile', $purchase);
        $purchase = $this->service->verifyWithGateway($purchase, 'manual_check');

        return back()->with('success', "Checked with {$purchase->gateway}: {$purchase->status->label()}.");
    }

    public function retry(PowerPurchase $purchase, Request $request): RedirectResponse
    {
        Gate::authorize('reconcile', $purchase);
        $data = $request->validate([
            'note' => ['required', 'string', 'max:250'],
            'confirm_no_token' => ['accepted'],
        ], ['confirm_no_token.accepted' => 'Confirm you checked the vendor portal and no token was issued.']);

        $purchase = $this->service->retryVend($purchase, $request->user(), $data['note']);

        return back()->with($purchase->status === PowerPurchaseStatus::TokenIssued ? 'success' : 'warning', "Vend retry finished: {$purchase->status->label()}.");
    }

    public function recordToken(PowerPurchase $purchase, Request $request): RedirectResponse
    {
        Gate::authorize('reconcile', $purchase);
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[0-9 \-]{16,40}$/'],
            'units' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'note' => ['required', 'string', 'max:250'],
        ]);

        $this->service->recordManualToken($purchase, $request->user(), $data['token'], isset($data['units']) ? number_format((float) $data['units'], 2, '.', '') : null, $data['note']);

        return back()->with('success', 'Token recorded and sent to the customer.');
    }

    public function confirmPayment(PowerPurchase $purchase, Request $request): RedirectResponse
    {
        Gate::authorize('reconcile', $purchase);
        $data = $request->validate(['amount_received' => ['required', 'string', 'max:15'], 'evidence' => ['required', 'string', 'max:250']]);

        try {
            $amount = Money::parse($data['amount_received']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['amount_received' => 'Enter a valid amount.']);
        }

        $purchase = $this->service->confirmPaymentManually($purchase, $request->user(), $amount, $data['evidence']);

        return back()->with('success', "Payment confirmed. Status: {$purchase->status->label()}.");
    }

    public function refund(PowerPurchase $purchase, Request $request): RedirectResponse
    {
        Gate::authorize('reconcile', $purchase);
        $data = $request->validate(['note' => ['required', 'string', 'max:250']]);
        $this->service->markRefunded($purchase, $request->user(), $data['note']);

        return back()->with('success', 'Purchase marked as refunded.');
    }
}
