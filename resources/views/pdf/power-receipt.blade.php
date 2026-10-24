@php $estate = $purchase->estate; $tz = $estate->timezone(); $token = $purchase->token; @endphp
<x-pdf-document :estate="$estate" title="Electricity receipt" :subtitle="$purchase->reference" :badge="$token ? ['TOKEN ISSUED', 'paid'] : [strtoupper($purchase->status->label()), 'due']">
    @if ($token)
        <div class="box" style="text-align:center; padding:18px">
            <span class="muted">Electricity token</span><br>
            <span class="token">{{ trim(chunk_split($token->token, 4, ' ')) }}</span><br>
            <span class="muted">{{ $token->units ? $token->units.' '.$token->unit_label.' · ' : '' }}Meter {{ $purchase->meter_number }}</span>
        </div>
    @endif
    <table class="items" style="margin-top:18px">
        <tbody>
            <tr><td class="muted" style="width:35%">Customer</td><td>{{ $purchase->user->name }}</td></tr>
            <tr><td class="muted">Property</td><td>{{ $purchase->property?->identifier ?? $purchase->meter?->vendor_address ?? '—' }}</td></tr>
            <tr><td class="muted">Meter number</td><td>{{ $purchase->meter_number }}</td></tr>
            <tr><td class="muted">Electricity value</td><td>{{ money($purchase->vend_amount) }}</td></tr>
            @if ($purchase->isOfficeVend())
                <tr><td class="muted">Payment</td><td><strong>None required — issued by the estate office</strong></td></tr>
                <tr><td class="muted">Issued by</td><td>{{ $purchase->issuer?->name }}{{ $purchase->office_note ? ' · '.$purchase->office_note : '' }}</td></tr>
            @else
                <tr><td class="muted">Service charge</td><td>{{ money($purchase->service_charge) }}</td></tr>
                <tr><td class="muted">Total paid</td><td><strong>{{ money($purchase->amount_paid ?? $purchase->total_amount) }}</strong></td></tr>
                <tr><td class="muted">Paid via</td><td>{{ ucfirst($purchase->gateway) }}{{ $purchase->payment_channel ? ' · '.humanize($purchase->payment_channel) : '' }}</td></tr>
                <tr><td class="muted">Paid at</td><td>{{ $purchase->paid_at?->timezone($tz)->format('j M Y, g:ia') }}</td></tr>
            @endif
            @if ($token)<tr><td class="muted">Token issued</td><td>{{ $token->issued_at->timezone($tz)->format('j M Y, g:ia') }}</td></tr>@endif
        </tbody>
    </table>
    @if (! $token)
        <p style="margin-top:18px" class="muted">Payment is confirmed; the token has not been issued yet ({{ $purchase->status->label() }}). The estate team will resolve this purchase.</p>
    @endif
    @if ($token?->source === 'demo_seed')<p class="muted">Demo data — not a real token.</p>@endif
</x-pdf-document>
