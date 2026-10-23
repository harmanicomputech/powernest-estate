<x-layouts.app title="Complete payment">
    <x-ui.page-header title="Complete payment" :back="route('power.buy')" />
    <div class="mx-auto max-w-md">
        @include('payments.inline-checkout', [
            'session' => $session,
            'amount' => $purchase->total_amount,
            'reference' => $purchase->reference,
            'verifyUrl' => route('power.purchases.verify', $purchase),
            'fallbackUrl' => route('power.purchases.show', $purchase),
            'lines' => ['Electricity' => money($purchase->vend_amount), 'Service charge' => money($purchase->service_charge), 'Meter' => $purchase->meter_number],
        ])
    </div>
</x-layouts.app>
