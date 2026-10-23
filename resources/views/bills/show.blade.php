<x-layouts.app :title="'Invoice '.$invoice->number">
    <x-ui.page-header :title="$invoice->number" :eyebrow="$invoice->property->identifier" :back="auth()->user()->can('billing.manage') ? route('manage.billing.invoices') : route('bills.index')">
        @if ($invoice->isOverdue())<x-ui.badge tone="danger" dot>Overdue</x-ui.badge>@else<x-ui.status :value="$invoice->status" />@endif
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <x-ui.card :padded="false">
                <div class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6">
                    <div>
                        <p class="text-lg font-bold">{{ $invoice->description }}</p>
                        <p class="mt-1 text-sm text-muted">Issued {{ $invoice->issue_date->format('j M Y') }} · Due {{ $invoice->due_date->format('j M Y') }}</p>
                        @if ($invoice->billedUser)<p class="text-sm text-muted">Billed to {{ $invoice->billedUser->name }}</p>@endif
                    </div>
                    <div class="text-right">
                        <p class="eyebrow">Balance</p>
                        <p class="text-2xl font-extrabold tnum">{{ money($invoice->balance()) }}</p>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead class="border-y border-line bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr><th class="px-5 py-2.5 sm:px-6">Item</th><th class="px-3 py-2.5 text-right">Qty</th><th class="px-5 py-2.5 text-right sm:px-6">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($invoice->items as $item)
                            <tr><td class="px-5 py-3 sm:px-6">{{ $item->description }}</td><td class="px-3 py-3 text-right tnum">{{ $item->quantity }}</td><td class="px-5 py-3 text-right font-semibold tnum sm:px-6">{{ money($item->amount) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-line text-sm">
                        @if ($invoice->adjustment_total)
                            <tr><td colspan="2" class="px-5 py-2 text-right text-muted sm:px-6">Discounts / waivers</td><td class="px-5 py-2 text-right tnum sm:px-6">−{{ money($invoice->adjustment_total) }}</td></tr>
                        @endif
                        <tr><td colspan="2" class="px-5 py-2 text-right text-muted sm:px-6">Total</td><td class="px-5 py-2 text-right font-bold tnum sm:px-6">{{ money($invoice->total) }}</td></tr>
                        <tr><td colspan="2" class="px-5 py-2 text-right text-muted sm:px-6">Paid</td><td class="px-5 py-2 text-right tnum sm:px-6">{{ money($invoice->amount_paid) }}</td></tr>
                    </tfoot>
                </table>
            </x-ui.card>

            @if ($invoice->allocations->isNotEmpty())
                <x-ui.card title="Payments" :padded="false">
                    @foreach ($invoice->allocations as $allocation)
                        <a href="{{ route('bills.receipt', $allocation->payment) }}" class="flex items-center justify-between border-t border-line px-5 py-3 text-sm hover:bg-canvas sm:px-6">
                            <span><span class="font-semibold">{{ humanize($allocation->payment->method) }}</span> <span class="text-muted">· {{ local_time($allocation->payment->paid_at, 'j M Y') }} · {{ $allocation->payment->reference }}</span></span>
                            <span class="font-semibold tnum">{{ money($allocation->amount) }}</span>
                        </a>
                    @endforeach
                </x-ui.card>
            @endif

            @can('manage', $invoice)
                @include('manage.billing.partials.invoice-actions')
            @endcan
        </div>

        <div class="lg:col-span-2">
            @if ($invoice->isPayable() && $gateways)
                <x-ui.card title="Pay this invoice">
                    <form method="POST" action="{{ route('bills.pay', $invoice) }}" class="space-y-3" x-data="submitting" x-on:submit="submit">
                        @csrf
                        @foreach ($gateways as $key => $gateway)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong p-3.5 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/60">
                                <input type="radio" name="gateway" value="{{ $key }}" class="accent-brand-700" @checked($loop->first)>
                                <span class="text-sm font-semibold">{{ $gateway->label() }}</span>
                            </label>
                        @endforeach
                        <x-ui.button class="w-full" size="lg" icon="lock" busy>Pay {{ money($invoice->balance()) }}</x-ui.button>
                    </form>
                    <p class="mt-3 text-xs text-muted">You can also pay by bank transfer to the estate account; the finance office will record it against this invoice.</p>
                </x-ui.card>
            @elseif ($invoice->status === \App\Enums\InvoiceStatus::Paid)
                <x-ui.alert tone="success" title="Paid in full">Thank you. Receipts are listed under Payments.</x-ui.alert>
            @elseif ($invoice->status === \App\Enums\InvoiceStatus::Void)
                <x-ui.alert tone="info" title="Voided">{{ $invoice->void_reason }}</x-ui.alert>
            @endif
        </div>
    </div>
</x-layouts.app>
