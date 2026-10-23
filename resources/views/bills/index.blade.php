<x-layouts.app title="Bills & payments">
    <x-ui.page-header title="Bills & payments" description="Estate service charges and other invoices for your property. Electricity purchases are kept separately.">
        <x-ui.button :href="route('transactions.index')" variant="secondary" icon="history">Transaction history</x-ui.button>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="relative overflow-hidden rounded-[var(--radius-card)] bg-brand-900 p-6 text-white sm:col-span-2">
            <div class="pointer-events-none absolute -right-10 -top-16 size-56 rounded-full bg-brand-600/40 blur-3xl"></div>
            <p class="relative text-sm text-brand-100/80">Total outstanding</p>
            <p class="relative mt-1 text-[2.5rem] font-extrabold leading-none tracking-tight tnum">{{ money($outstandingTotal) }}</p>
            <p class="relative mt-3 text-sm text-brand-100/80">
                @if ($nextDue)Next due {{ $nextDue->due_date->format('j F Y') }}@if($overdueCount) · <span class="font-semibold text-gold-200">{{ $overdueCount }} overdue</span>@endif @else Nothing outstanding — thank you! @endif
            </p>
            @if ($nextDue)
                <x-ui.button :href="route('bills.show', $nextDue)" variant="gold" class="relative mt-5" icon-right="arrow-right">Pay {{ $nextDue->number }}</x-ui.button>
            @endif
        </div>
        <x-ui.card title="Recent payments" :padded="false">
            @forelse ($payments as $payment)
                <a href="{{ route('bills.receipt', $payment) }}" class="flex items-center justify-between border-t border-line px-5 py-3 text-sm hover:bg-canvas">
                    <span class="text-muted">{{ local_time($payment->paid_at, 'j M') }}</span>
                    <span class="font-semibold tnum">{{ money($payment->amount_received ?? $payment->amount) }}</span>
                </a>
            @empty
                <p class="border-t border-line px-5 py-5 text-sm text-muted">No payments yet.</p>
            @endforelse
        </x-ui.card>
    </div>

    <h2 class="mb-3 mt-8 text-[0.9375rem] font-bold">Invoices</h2>
    <x-ui.card :padded="false">
        @forelse ($invoices as $invoice)
            <a href="{{ route('bills.show', $invoice) }}" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-canvas sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-sunken text-ink-soft"><x-icon name="receipt" class="size-[1.125rem]" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $invoice->description }}</p>
                    <p class="text-sm text-muted">{{ $invoice->number }} · {{ $invoice->property->identifier }} · due {{ $invoice->due_date->format('j M Y') }}</p>
                </div>
                <div class="text-right">
                    <p class="font-bold tnum">{{ money($invoice->total) }}</p>
                    @if ($invoice->isOverdue())<x-ui.badge tone="danger" dot>Overdue</x-ui.badge>@else<x-ui.status :value="$invoice->status" />@endif
                </div>
            </a>
        @empty
            <x-ui.empty icon="receipt" title="No invoices yet" description="When the estate issues service charges or other bills for your property, they'll appear here." />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
