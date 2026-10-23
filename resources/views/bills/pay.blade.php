<x-layouts.app title="Pay invoice">
    <x-ui.page-header title="Pay {{ $invoice->number }}" :back="route('bills.show', $invoice)" />
    <div class="mx-auto max-w-md">
        @include('payments.inline-checkout', [
            'session' => $session,
            'amount' => $payment->amount,
            'reference' => $payment->reference,
            'verifyUrl' => route('bills.payments.verify', $payment),
            'fallbackUrl' => route('bills.show', $invoice),
            'lines' => ['Invoice' => $invoice->number, 'For' => $invoice->description],
        ])
    </div>
</x-layouts.app>
