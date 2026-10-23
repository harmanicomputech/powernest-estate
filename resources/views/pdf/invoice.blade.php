@php
    $estate = $invoice->estate;
    $tz = $estate->timezone();
    $badge = match (true) {
        $invoice->status === \App\Enums\InvoiceStatus::Paid => ['PAID', 'paid'],
        $invoice->status === \App\Enums\InvoiceStatus::Void => ['VOID', 'void'],
        default => [$invoice->isOverdue() ? 'OVERDUE' : 'DUE '.$invoice->due_date->format('j M Y'), 'due'],
    };
@endphp
<x-pdf-document :estate="$estate" :title="'Invoice'" :subtitle="$invoice->number" :badge="$badge">
    <table class="meta">
        <tr>
            <td style="width:50%"><div class="box"><span class="muted">BILLED TO</span><br><strong>{{ $invoice->billedUser?->name ?? 'Occupant' }}</strong><br>{{ $invoice->property->identifier }}{{ $invoice->property->address_line ? ', '.$invoice->property->address_line : '' }}</div></td>
            <td style="width:4%"></td>
            <td><div class="box">
                <span class="muted">Issue date</span> <strong style="float:right">{{ $invoice->issue_date->format('j M Y') }}</strong><br>
                <span class="muted">Due date</span> <strong style="float:right">{{ $invoice->due_date->format('j M Y') }}</strong><br>
                @if ($invoice->period_start)<span class="muted">Period</span> <strong style="float:right">{{ $invoice->period_start->format('j M Y') }} – {{ $invoice->period_end?->format('j M Y') }}</strong>@endif
            </div></td>
        </tr>
    </table>

    <p style="margin:18px 0 8px"><strong>{{ $invoice->description }}</strong></p>
    <table class="items">
        <thead><tr><th>Description</th><th class="right" style="width:12%">Qty</th><th class="right" style="width:20%">Unit</th><th class="right" style="width:20%">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr><td>{{ $item->description }}</td><td class="right">{{ $item->quantity }}</td><td class="right">{{ money($item->unit_amount) }}</td><td class="right">{{ money($item->amount) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals" style="width:45%; margin-left:55%; margin-top:10px">
        <tr><td class="muted">Subtotal</td><td class="right">{{ money($invoice->subtotal) }}</td></tr>
        @if ($invoice->adjustment_total)<tr><td class="muted">Discounts / waivers</td><td class="right">−{{ money($invoice->adjustment_total) }}</td></tr>@endif
        <tr><td class="muted">Total</td><td class="right">{{ money($invoice->total) }}</td></tr>
        <tr><td class="muted">Paid</td><td class="right">{{ money($invoice->amount_paid) }}</td></tr>
        <tr class="grand"><td>Balance due</td><td class="right">{{ money($invoice->balance()) }}</td></tr>
    </table>

    @if ($invoice->allocations->isNotEmpty())
        <p style="margin:22px 0 8px"><strong>Payments received</strong></p>
        <table class="items">
            <thead><tr><th>Date</th><th>Reference</th><th>Method</th><th class="right">Amount</th></tr></thead>
            <tbody>
                @foreach ($invoice->allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->payment->paid_at?->timezone($tz)->format('j M Y') }}</td>
                        <td>{{ $allocation->payment->reference }}{{ $allocation->payment->status === \App\Enums\PaymentStatus::Reversed ? ' (reversed)' : '' }}</td>
                        <td>{{ humanize($allocation->payment->method) }}</td>
                        <td class="right">{{ money($allocation->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($invoice->status === \App\Enums\InvoiceStatus::Void)
        <p style="margin-top:18px" class="muted">This invoice was voided: {{ $invoice->void_reason }}</p>
    @elseif ($invoice->balance() > 0)
        <p style="margin-top:22px" class="muted">Pay online in the PowerNest Estate app, or pay by bank transfer and upload your receipt on this invoice for verification. Quote {{ $invoice->number }} as the payment reference.</p>
    @endif
</x-pdf-document>
