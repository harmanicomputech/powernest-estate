<x-layouts.app title="Transactions">
    <x-ui.page-header title="Transactions" description="Every payment, receipt and electricity purchase for your home. Download invoices and receipts as PDF." />

    <div class="mb-6 grid grid-cols-3 gap-3 sm:gap-4">
        <x-ui.stat label="Estate payments {{ now()->year }}" :value="money($totals['paidThisYear'], false)" icon="receipt" />
        <x-ui.stat label="Electricity {{ now()->year }}" :value="money($totals['electricityThisYear'], false)" icon="zap" tone="gold" />
        <x-ui.stat label="Outstanding" :value="money($totals['outstanding'], false)" icon="wallet" :tone="$totals['outstanding'] ? 'warning' : 'brand'" :href="$totals['outstanding'] ? route('bills.index') : null" />
    </div>

    <x-ui.tabs :active="$tab" :tabs="array_filter([
        'all' => ['All', route('transactions.index')],
        'estate' => auth()->user()->can('billing.view_own') ? ['Estate payments', route('transactions.index', ['tab' => 'estate'])] : null,
        'electricity' => auth()->user()->can('power.purchase') ? ['Electricity', route('transactions.index', ['tab' => 'electricity'])] : null,
        'invoices' => auth()->user()->can('billing.view_own') ? ['Invoices', route('transactions.index', ['tab' => 'invoices'])] : null,
    ])" />

    @if ($tab === 'invoices')
        <x-ui.card :padded="false">
            @forelse ($invoices ?? [] as $invoice)
                <div class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-sunken text-ink-soft"><x-icon name="file-text" class="size-[1.125rem]" /></span>
                    <a href="{{ route('bills.show', $invoice) }}" class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $invoice->description }}</p>
                        <p class="text-sm text-muted">{{ $invoice->number }} · {{ $invoice->property->identifier }} · issued {{ $invoice->issue_date->format('j M Y') }}</p>
                    </a>
                    <div class="text-right">
                        <p class="font-bold tnum">{{ money($invoice->total) }}</p>
                        @if ($invoice->isOverdue())<x-ui.badge tone="danger" dot>Overdue</x-ui.badge>@else<x-ui.status :value="$invoice->status" />@endif
                    </div>
                    <x-ui.button :href="route('pdf.invoice', $invoice)" variant="secondary" size="sm" icon="download" aria-label="Download {{ $invoice->number }} as PDF">PDF</x-ui.button>
                </div>
            @empty
                <x-ui.empty icon="file-text" title="No invoices yet" />
            @endforelse
        </x-ui.card>
        @if ($invoices)<div class="mt-4">{{ $invoices->links() }}</div>@endif
    @else
        <x-ui.card :padded="false">
            @forelse ($items as $item)
                <div class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                    <span @class(['grid size-10 shrink-0 place-items-center rounded-xl', 'bg-gold-50 text-gold-600' => $item['kind'] === 'electricity', 'bg-brand-50 text-brand-700' => $item['kind'] === 'estate'])><x-icon :name="$item['icon']" class="size-[1.125rem]" /></span>
                    <a href="{{ $item['url'] }}" class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $item['title'] }}</p>
                        <p class="truncate text-sm text-muted">{{ local_time($item['date'], 'j M Y, g:ia') }} · <span class="font-mono text-xs">{{ $item['subtitle'] }}</span></p>
                    </a>
                    <div class="text-right">
                        <p class="font-bold tnum">{{ money($item['amount']) }}</p>
                        <x-ui.badge :tone="$item['tone']" dot>{{ $item['status'] }}</x-ui.badge>
                    </div>
                    @if ($item['pdf'])
                        <x-ui.button :href="$item['pdf']" variant="ghost" size="icon" icon="download" aria-label="Download {{ strtolower($item['pdfLabel']) }} PDF" />
                    @else
                        <span class="size-10"></span>
                    @endif
                </div>
            @empty
                <x-ui.empty icon="history" title="No transactions yet" description="Payments, uploaded receipts and electricity purchases will appear here." />
            @endforelse
        </x-ui.card>
        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</x-layouts.app>
