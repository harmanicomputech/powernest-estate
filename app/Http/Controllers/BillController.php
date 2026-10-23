<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Integrations\IntegrationException;
use App\Integrations\Payments\GatewayManager;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\PaymentSubmissionService;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

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
            'invoice' => $invoice->load('items', 'property', 'billedUser', 'allocations.payment', 'adjustments', 'submissions.reviewer'),
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

    public function submitReceipt(Invoice $invoice, Request $request, PaymentSubmissionService $submissions): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:15'],
            'method' => ['required', Rule::in(array_keys(PaymentSubmission::METHODS))],
            'payer_reference' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ], ['receipt.required' => 'Attach a photo or PDF of your receipt.']);

        try {
            $data['amount'] = Money::parse($data['amount']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter a valid amount.']);
        }

        $submission = $submissions->submit($request->user(), $invoice, $data, $request->file('receipt'));

        return back()->with('success', "Receipt {$submission->reference} uploaded. The estate office will verify it and you'll be notified.");
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
        abort_unless(in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::Reversed], true), 404);

        return view('bills.receipt', ['payment' => $payment->load('invoice', 'property', 'payer', 'allocations.invoice', 'reverser')]);
    }
}
