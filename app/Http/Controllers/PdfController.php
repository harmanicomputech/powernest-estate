<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PowerPurchase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloadable PDF documents. Each is authorized with the same policy as its on-screen page.
 */
class PdfController extends Controller
{
    public function invoice(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        return $this->download('pdf.invoice', ['invoice' => $invoice->load('estate', 'property', 'billedUser', 'items', 'allocations.payment')], "{$invoice->number}.pdf");
    }

    public function payment(Payment $payment): Response
    {
        Gate::authorize('view', $payment);
        abort_unless(in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::Reversed], true), 404);

        return $this->download('pdf.payment-receipt', ['payment' => $payment->load('estate', 'property', 'payer', 'allocations.invoice')], "Receipt-{$payment->reference}.pdf");
    }

    public function purchase(PowerPurchase $purchase): Response
    {
        Gate::authorize('view', $purchase);
        abort_unless($purchase->status->isPaymentConfirmed(), 404);

        return $this->download('pdf.power-receipt', ['purchase' => $purchase->load('estate', 'user', 'property', 'token')], "Electricity-{$purchase->reference}.pdf");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function download(string $view, array $data, string $filename): Response
    {
        $response = Pdf::loadView($view, $data)->setPaper('a4')->download($filename);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
