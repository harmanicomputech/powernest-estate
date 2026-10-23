<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Integrations\IntegrationException;
use App\Integrations\Payments\CheckoutSession;
use App\Integrations\Payments\GatewayManager;
use App\Integrations\Payments\PaymentIntent;
use App\Integrations\Payments\PaymentVerification;
use App\Integrations\Payments\VerificationStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceipt;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Online payment of estate invoices through the shared gateway adapters. Kept separate from
 * electricity vending: different reference prefix (PNB-), records and ledger.
 */
class BillingPaymentService
{
    public function __construct(private GatewayManager $gateways, private BillingService $billing, private AuditLogger $audit) {}

    public function start(User $user, Invoice $invoice, string $gateway): array
    {
        abort_unless($user->hasEstatePermission($invoice->estate, Permission::BillingViewOwn), 403);
        abort_unless($user->authorizedProperties($invoice->estate_id)->whereKey($invoice->property_id)->exists(), 403);

        if (! $invoice->isPayable()) {
            throw ValidationException::withMessages(['invoice' => 'This invoice has nothing left to pay.']);
        }

        if (! array_key_exists($gateway, $this->gateways->availableFor($invoice->estate))) {
            throw ValidationException::withMessages(['gateway' => 'This payment method is not available.']);
        }

        $payment = Payment::create([
            'estate_id' => $invoice->estate_id,
            'property_id' => $invoice->property_id,
            'payer_id' => $user->id,
            'invoice_id' => $invoice->id,
            'reference' => $this->billing->uniquePaymentReference(),
            'purpose' => 'estate_invoice',
            'method' => $gateway,
            'currency' => 'NGN',
            'amount' => $invoice->balance(),
            'status' => PaymentStatus::Pending,
        ]);

        $session = $this->gateways->get($gateway)->initialize(new PaymentIntent(
            reference: $payment->reference,
            amount: $payment->amount,
            currency: $payment->currency,
            email: $user->email,
            customerName: $user->name,
            phone: $user->phone,
            callbackUrl: route('bills.payments.return', $payment),
            description: "Invoice {$invoice->number}",
            metadata: ['purpose' => 'estate_invoice', 'invoice' => $invoice->number],
        ));

        return [$payment, $session];
    }

    public function verifyWithGateway(Payment $payment, string $via): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        try {
            $verification = $this->gateways->get($payment->method)->verify($payment->reference);
        } catch (IntegrationException $e) {
            Log::warning('Billing payment verification unavailable', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

            return $payment;
        }

        return $this->apply($payment, $verification, $via);
    }

    public function apply(Payment $payment, PaymentVerification $verification, string $via): Payment
    {
        $succeeded = false;

        $payment = DB::transaction(function () use ($payment, $verification, $via, &$succeeded) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Pending || $verification->reference !== $locked->reference) {
                return $locked;
            }

            if ($verification->status === VerificationStatus::Failed) {
                $locked->update(['status' => PaymentStatus::Failed, 'notes' => $verification->message]);

                return $locked;
            }

            if (! $verification->succeeded()) {
                return $locked;
            }

            if (($verification->currency !== null && $verification->currency !== $locked->currency) || $verification->amount === null || $verification->amount < $locked->amount) {
                $locked->update(['notes' => 'Needs review: received '.($verification->amount ?? 'unknown').' '.($verification->currency ?? '').' via '.$via]);
                Log::warning('Billing payment amount/currency mismatch', ['reference' => $locked->reference]);

                return $locked;
            }

            $invoice = Invoice::whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();
            $allocatable = min($locked->amount, $invoice->balance());

            $locked->update([
                'status' => PaymentStatus::Succeeded,
                'amount_received' => $verification->amount,
                'gateway_reference' => $verification->gatewayReference,
                'paid_at' => $verification->paidAt ?? now(),
            ]);

            if ($allocatable > 0) {
                $this->billing->allocate($locked, $invoice, $allocatable, null);
            }

            $this->audit->record('payment.confirmed', $locked, ['via' => $via, 'invoice' => $invoice->number], $locked->estate_id);
            $succeeded = true;

            return $locked;
        });

        if ($succeeded) {
            $payment->payer?->notify(new PaymentReceipt($payment));
        }

        return $payment;
    }
}
