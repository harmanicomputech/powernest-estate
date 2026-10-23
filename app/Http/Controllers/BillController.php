<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Integrations\IntegrationException;
use App\Integrations\Payments\GatewayManager;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillingPaymentService;
use App\Support\EstateContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BillController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();
        $propertyIds = $request->user()->authorizedProperties($estate)->pluck('properties.id');

        $invoices = Invoice::forEstate($estate)->whereIn('property_id', $propertyIds)->with('property')->latest('issue_date')->paginate(20);
        $outstanding = Invoice::forEstate($estate)->whereIn('property_id', $propertyIds)->outstanding()->get();

        $payments = Payment::forEstate($estate)->whereIn('property_id', $propertyIds)->where('status', PaymentStatus::Succeeded->value)->latest('paid_at')->limit(5)->get();

        return view('bills.index', [
            'invoices' => $invoices,
            'outstandingTotal' => $outstanding->sum(fn (Invoice $i) => $i->balance()),
            'overdueCount' => $outstanding->filter->isOverdue()->count(),
            'nextDue' => $outstanding->sortBy('due_date')->first(),
            'payments' => $payments,
        ]);
    }

    public function show(Invoice $invoice, GatewayManager $gateways): View
    {
        Gate::authorize('view', $invoice);

        return view('bills.show', [
            'invoice' => $invoice->load('items', 'property', 'billedUser', 'allocations.payment', 'adjustments'),
            'gateways' => Gate::allows('pay', $invoice) ? $gateways->availableFor($invoice->estate) : [],
        ]);
    }

    public function pay(Invoice $invoice, Request $request, BillingPaymentService $payments): RedirectResponse|View
    {
        Gate::authorize('pay', $invoice);
        $data = $request->validate(['gateway' => ['required', 'in:payaza,paystack']]);

        try {
            [$payment, $session] = $payments->start($request->user(), $invoice, $data['gateway']);
        } catch (IntegrationException) {
            return back()->with('error', 'The payment provider is unavailable right now. Nothing was charged.');
        }

        if ($session->mode === 'redirect') {
            return redirect()->away($session->redirectUrl);
        }

        return view('bills.pay', ['invoice' => $invoice, 'payment' => $payment, 'session' => $session]);
    }

    public function paymentReturn(Payment $payment, BillingPaymentService $payments): RedirectResponse
    {
        Gate::authorize('view', $payment);
        $payment = $payments->verifyWithGateway($payment, 'callback');

        return $payment->status === PaymentStatus::Succeeded
            ? redirect()->route('bills.receipt', $payment)->with('success', 'Payment confirmed. Thank you.')
            : redirect()->route('bills.show', $payment->invoice)->with('warning', 'We have not received confirmation of this payment yet. It will update automatically once confirmed.');
    }

    public function verifyPayment(Payment $payment, BillingPaymentService $payments): JsonResponse
    {
        Gate::authorize('view', $payment);
        $payment = $payments->verifyWithGateway($payment, 'callback');

        return response()->json([
            'status' => $payment->status->value,
            'redirect' => $payment->status === PaymentStatus::Succeeded ? route('bills.receipt', $payment) : route('bills.show', $payment->invoice),
        ]);
    }

    public function receipt(Payment $payment): View
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->status === PaymentStatus::Succeeded, 404);

        return view('bills.receipt', ['payment' => $payment->load('invoice', 'property', 'payer', 'allocations.invoice')]);
    }
}
