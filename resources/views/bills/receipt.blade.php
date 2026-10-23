<x-layouts.app title="Receipt">
    <x-ui.page-header title="Payment receipt" :back="$payment->invoice ? route('bills.show', $payment->invoice) : route('bills.index')">
        <x-ui.button type="button" variant="secondary" icon="download" onclick="window.print()">Print</x-ui.button>
    </x-ui.page-header>
    <div class="mx-auto max-w-lg">
        @if ($payment->status === \App\Enums\PaymentStatus::Reversed)
            <x-ui.alert tone="danger" title="This payment was reversed" class="mb-4">{{ local_time($payment->reversed_at) }} by {{ $payment->reverser?->name }}: {{ $payment->reversal_reason }}</x-ui.alert>
        @endif
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line p-6">
                <x-brand.logo />
                <div class="text-right">
                    <p class="eyebrow">Receipt</p>
                    <p class="font-mono text-sm font-semibold">{{ $payment->reference }}</p>
                </div>
            </div>
            <div class="p-6 text-center">
                <span class="mx-auto grid size-14 place-items-center rounded-full bg-success-soft text-success"><x-icon name="check" class="size-7" stroke="2.5" /></span>
                <p class="mt-4 text-sm text-muted">Amount received</p>
                <p class="text-[2.25rem] font-extrabold tracking-tight tnum">{{ money($payment->amount_received ?? $payment->amount) }}</p>
                <p class="text-sm text-muted">{{ local_time($payment->paid_at, 'j F Y, g:ia') }}</p>
            </div>
            <div class="border-t border-line p-6">
                <x-ui.dl :items="[
                    'Estate' => $payment->estate->name,
                    'Property' => $payment->property?->identifier,
                    'Paid by' => $payment->payer?->name,
                    'Method' => humanize($payment->method),
                    'External reference' => $payment->external_reference ?? $payment->gateway_reference,
                    'Applied to' => $payment->allocations->map(fn ($a) => $a->invoice->number.' ('.money($a->amount).')')->join(', '),
                    'Purpose' => 'Estate charges',
                ]" />
            </div>
        </div>
        @can('billing.approve')
            @if ($payment->status === \App\Enums\PaymentStatus::Succeeded)
                <div class="mt-4 print:hidden">
                    <x-ui.confirm :action="route('manage.billing.payments.reverse', $payment)" title="Reverse this payment?" message="Use for bounced transfers, chargebacks or refunds paid outside PowerNest. The invoice becomes payable again and the ledger records the reversal." confirm="Reverse payment" confirm-variant="danger" variant="danger-soft" class="w-full" icon="refresh-cw">
                        Reverse payment
                        <x-slot:fields><x-ui.input name="reason" label="Reason" required /></x-slot:fields>
                    </x-ui.confirm>
                </div>
            @endif
        @endcan
    </div>
</x-layouts.app>
