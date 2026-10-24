<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PowerPurchase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

        return $this->download('pdf.power-receipt', ['purchase' => $purchase->load('estate', 'user', 'issuer', 'property', 'token')], "Electricity-{$purchase->reference}.pdf");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function download(string $view, array $data, string $filename): Response
    {
        // Shared hosts often default to 64M, which is not enough for embedding the Unicode font (₦).
        if ($this->memoryLimitBytes() < 256 * 1024 * 1024) {
            @ini_set('memory_limit', '256M');
        }
        @set_time_limit(60);

        $fonts = storage_path('fonts');
        $temp = storage_path('app/pdf-temp');
        foreach ([$fonts, $temp] as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        try {
            $response = Pdf::setOption(['font_dir' => $fonts, 'font_cache' => $fonts, 'temp_dir' => $temp])
                ->loadView($view, $data)->setPaper('a4')->download($filename);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The PDF could not be created right now. Use the Print button instead, or try again later.');
        }

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function memoryLimitBytes(): int
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
