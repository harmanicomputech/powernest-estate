<x-layouts.app title="Billing" wide>
    <x-ui.page-header title="Billing" description="Estate charges, collections and approvals. Electricity sales are tracked separately under Power operations.">
        <x-ui.button :href="route('manage.billing.invoices')" variant="secondary" icon="receipt">All invoices</x-ui.button>
        <x-ui.button :href="route('manage.billing.invoices.create')" icon="plus">New invoice</x-ui.button>
    </x-ui.page-header>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <x-ui.stat label="Outstanding" :value="money($outstanding, false)" icon="wallet" :href="route('manage.billing.invoices')" />
        <x-ui.stat label="Overdue" :value="money($overdue, false)" icon="hourglass" tone="danger" :hint="$overdueCount.' invoices'" :href="route('manage.billing.invoices', ['status' => 'overdue'])" />
        <x-ui.stat label="Collected this month" :value="money($collected, false)" icon="banknote" tone="gold" class="col-span-2 lg:col-span-1" />
    </div>

    @if ($pendingReceipts)
        <a href="{{ route('manage.billing.submissions.index') }}" class="mt-6 flex items-center gap-3 rounded-[var(--radius-card)] border border-[#c6d8f2] bg-info-soft p-4">
            <span class="grid size-10 place-items-center rounded-xl bg-white text-info"><x-icon name="file-check" class="size-5" /></span>
            <span class="flex-1 font-semibold">{{ $pendingReceipts }} offline payment receipt(s) waiting for verification</span>
            <x-icon name="chevron-right" class="size-5 text-info" />
        </a>
    @endif

    @if ($adjustments->isNotEmpty())
        <x-ui.card title="Discounts & waivers awaiting approval" class="mt-6" :padded="false">
            @foreach ($adjustments as $adjustment)
                <div class="flex flex-wrap items-center gap-3 border-t border-line px-5 py-3.5 sm:px-6">
                    <div class="min-w-0 flex-1 text-sm"><p><span class="font-semibold">{{ ucfirst($adjustment->type) }} of {{ money($adjustment->amount) }}</span> on <a class="link" href="{{ route('bills.show', $adjustment->invoice) }}">{{ $adjustment->invoice->number }}</a> ({{ $adjustment->invoice->property->identifier }})</p><p class="text-muted">“{{ $adjustment->reason }}” — {{ $adjustment->requester?->name }}</p></div>
                    @can('billing.approve')
                        @if ($adjustment->requested_by !== auth()->id())
                            <form method="POST" action="{{ route('manage.billing.adjustments.review', $adjustment) }}" class="flex gap-1.5">@csrf<x-ui.button name="decision" value="reject" size="sm" variant="ghost">Reject</x-ui.button><x-ui.button name="decision" value="approve" size="sm">Approve</x-ui.button></form>
                        @else
                            <x-ui.badge tone="warning">Needs another approver</x-ui.badge>
                        @endif
                    @endcan
                </div>
            @endforeach
        </x-ui.card>
    @endif

    @if ($needsReview->isNotEmpty())
        <x-ui.alert tone="warning" title="Online payments needing review" class="mt-6">
            @foreach ($needsReview as $payment){{ $payment->reference }} ({{ $payment->invoice?->number }}): {{ $payment->notes }}<br>@endforeach
        </x-ui.alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Recurring charges" :padded="false">
            <x-slot:action><x-ui.button type="button" size="sm" variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'charge-type')">Add</x-ui.button></x-slot:action>
            @forelse ($chargeTypes as $type)
                <div class="flex flex-wrap items-center gap-3 border-t border-line px-5 py-3.5 sm:px-6" x-data="{ open: false }">
                    <div class="min-w-0 flex-1"><p class="font-semibold">{{ $type->name }} <span class="font-mono text-xs text-muted">{{ $type->code }}</span></p><p class="text-sm text-muted">{{ money($type->amount) }} · {{ humanize($type->frequency) }} · {{ $type->invoices_count }} invoices</p></div>
                    <x-ui.button type="button" size="sm" x-on:click="open = !open">Run</x-ui.button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('manage.billing.charge-types.run', $type) }}" class="grid w-full grid-cols-3 gap-2">@csrf
                        <x-ui.input name="period" type="month" :value="$type->frequency === 'annual' ? now()->startOfYear()->format('Y-m') : now()->format('Y-m')" required />
                        <x-ui.input name="due_date" type="date" :value="now()->addDays(14)->toDateString()" required />
                        <x-ui.button size="sm" class="self-end">Issue invoices</x-ui.button>
                        <p class="col-span-3 text-xs text-muted">Bills every occupied property once per period. Re-running skips properties already billed.</p>
                    </form>
                </div>
            @empty
                <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">Set up service charges to bill properties in bulk.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card title="Recent payments" :padded="false">
            @forelse ($recentPayments as $payment)
                <a href="{{ route('bills.receipt', $payment) }}" class="flex items-center gap-3 border-t border-line px-5 py-3 text-sm hover:bg-canvas sm:px-6">
                    <span class="min-w-0 flex-1 truncate"><span class="font-semibold">{{ $payment->property?->identifier }}</span> <span class="text-muted">· {{ $payment->invoice?->number }} · {{ humanize($payment->method) }}</span></span>
                    <span class="text-muted">{{ local_time($payment->paid_at, 'j M') }}</span>
                    <span class="font-semibold tnum">{{ money($payment->amount_received) }}</span>
                </a>
            @empty
                <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">No payments yet.</p>
            @endforelse
        </x-ui.card>
    </div>

    <x-ui.modal name="charge-type" title="New recurring charge">
        <form method="POST" action="{{ route('manage.billing.charge-types.store') }}" class="space-y-4">@csrf
            <x-ui.input name="name" label="Name" required placeholder="Service charge" />
            <div class="grid grid-cols-2 gap-3"><x-ui.input name="code" label="Code" required placeholder="SVC" class="uppercase" /><x-ui.input name="amount" label="Amount" prefix="₦" required /></div>
            <x-ui.select name="frequency" label="Frequency" :options="['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual', 'one_time' => 'One-time']" />
            <x-ui.button class="w-full">Save</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
