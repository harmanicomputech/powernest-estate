<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\PowerPurchase;
use App\Notifications\PowerTokenIssued;
use App\Services\Power\PowerPurchaseService;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Estate managers/administrators issue electricity tokens without payment: enter the meter number
 * and the value, the token is vended and emailed to the meter's main occupant, and can be printed.
 */
class OfficeVendController extends Controller
{
    public function __construct(private EstateContext $context, private PowerPurchaseService $service) {}

    public function create(Request $request): View
    {
        $estate = $this->context->estate();
        $number = preg_replace('/\D/', '', (string) $request->query('meter'));
        $meter = $number !== '' ? Meter::forEstate($estate)->where('meter_number', $number)->with('property.occupancies.user')->first() : null;

        return view('manage.office-vend.create', [
            'number' => $number,
            'meter' => $meter,
            'owner' => $meter?->property?->occupancies->where('status', 'active')->sortByDesc('is_primary')->first()?->user,
            'recent' => PowerPurchase::forEstate($estate)->where('gateway', 'office')->with('user', 'issuer')->latest()->limit(8)->get(),
            'max' => (int) $estate->setting('power.max_amount'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'meter_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:15'],
            'note' => ['required', 'string', 'max:200'],
        ]);
        $meter = Meter::forEstate($estate)->findOrFail($data['meter_id']);

        $purchase = $this->service->issueWithoutPayment($estate, $request->user(), $meter, Money::parse($data['amount']), $data['note']);

        return redirect()->route('manage.office-vend.show', $purchase)->with(
            $purchase->token ? 'success' : 'warning',
            $purchase->token ? 'Token issued and emailed to '.$purchase->user->name.'.' : 'The token is not ready yet: '.$purchase->status->label().'.',
        );
    }

    public function show(PowerPurchase $purchase): View
    {
        Gate::authorize('view', $purchase);
        abort_unless($purchase->isOfficeVend(), 404);

        return view('manage.office-vend.show', ['purchase' => $purchase->load('user', 'issuer', 'property', 'token')]);
    }

    public function resend(PowerPurchase $purchase): RedirectResponse
    {
        Gate::authorize('view', $purchase);
        abort_unless($purchase->isOfficeVend() && $purchase->token, 404);

        $purchase->user->notify(new PowerTokenIssued($purchase));

        return back()->with('success', 'Token emailed to '.$purchase->user->email.'.');
    }
}
