@php $estate = $payment->estate; $tz = $estate->timezone(); $reversed = $payment->status === \App\Enums\PaymentStatus::Reversed; @endphp
<x-pdf-document :estate="$estate" title="Payment receipt" :subtitle="$payment->reference" :badge="$reversed ? ['REVERSED', 'reversed'] : ['PAID', 'paid']">
    <div class="box" style="text-align:center; padding:18px">
        <span class="muted">Amount received</span><br>
        <span style="font-size:24px; font-weight:bold">{{ money($payment->amount_received ?? $payment->amount) }}</span><br>
        <span class="muted">{{ $payment->paid_at?->timezone($tz)->format('j F Y, g:ia') }}</span>
    </div>
    <table class="items" style="margin-top:18px">
        <tbody>
            <tr><td class="muted" style="width:35%">Received from</td><td>{{ $payment->payer?->name ?? 'Occupant' }}</td></tr>
            <tr><td class="muted">Property</td><td>{{ $payment->property?->identifier }}</td></tr>
            <tr><td class="muted">Method</td><td>{{ humanize($payment->method) }}</td></tr>
            <tr><td class="muted">Reference</td><td>{{ $payment->external_reference ?? $payment->gateway_reference ?? $payment->reference }}</td></tr>
            <tr><td class="muted">Applied to</td><td>@foreach ($payment->allocations as $a){{ $a->invoice->number }} — {{ $a->invoice->description }} ({{ money($a->amount) }})@if(! $loop->last)<br>@endif @endforeach</td></tr>
            <tr><td class="muted">Purpose</td><td>Estate charges</td></tr>
        </tbody>
    </table>
    @if ($reversed)
        <p style="margin-top:18px; color:#b42318">This payment was reversed on {{ $payment->reversed_at?->timezone($tz)->format('j M Y') }}: {{ $payment->reversal_reason }}</p>
    @endif
</x-pdf-document>
