<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PowerPurchase;
use App\Services\Billing\BillingPaymentService;
use App\Services\Power\PowerPurchaseService;

/**
 * Routes a gateway reference to the module that owns it. Electricity purchases (PNE-) and
 * estate payments (PNB-) never share records or processing paths.
 */
class PaymentReferenceRouter
{
    public function __construct(private PowerPurchaseService $power, private BillingPaymentService $billing) {}

    public function verify(string $gateway, string $reference, string $via): void
    {
        if (str_starts_with($reference, 'PNE-')) {
            $purchase = PowerPurchase::where('reference', $reference)->where('gateway', $gateway)->first();
            $purchase && $this->power->verifyWithGateway($purchase, $via);

            return;
        }

        if (str_starts_with($reference, 'PNB-')) {
            $payment = Payment::where('reference', $reference)->where('method', $gateway)->first();
            $payment && $this->billing->verifyWithGateway($payment, $via);
        }
    }
}
