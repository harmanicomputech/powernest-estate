<x-layouts.app title="Expenses" wide>
    <x-ui.page-header title="Expenses" description="Vendor invoices and estate spending, with approval.">
        <x-ui.button type="button" variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'vendor')">Vendor</x-ui.button>
        <x-ui.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'expense')">Record expense</x-ui.button>
    </x-ui.page-header>
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4">
        <x-ui.stat label="Approved this month" :value="money($monthTotal, false)" icon="wallet" />
        <x-ui.stat label="Awaiting approval" :value="money($pendingTotal, false)" icon="hourglass" tone="warning" :href="route('manage.expenses.index', ['status' => 'pending_approval'])" />
    </div>
    <x-ui.tabs :active="$status ?? 'all'" :tabs="['all' => ['All', route('manage.expenses.index')]] + collect(\App\Enums\ExpenseStatus::cases())->mapWithKeys(fn ($s) => [$s->value => [$s->label(), route('manage.expenses.index', ['status' => $s->value])]])->all()" />
    <x-ui.card :padded="false">
        @forelse ($expenses as $expense)
            <div class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $expense->description }}</p>
                    <p class="text-sm text-muted">{{ $categories[$expense->category] ?? $expense->category }} · {{ $expense->vendor?->name ?? 'No vendor' }} · {{ $expense->incurred_on->format('j M Y') }} · by {{ $expense->submitter?->name }}</p>
                </div>
                <span class="font-bold tnum">{{ money($expense->amount) }}</span>
                <x-ui.status :value="$expense->status" />
                @can('expenses.approve')
                    @if ($expense->status === \App\Enums\ExpenseStatus::PendingApproval && $expense->submitted_by !== auth()->id())
                        <form method="POST" action="{{ route('manage.expenses.review', $expense) }}" class="flex gap-1.5">@csrf<x-ui.button name="decision" value="reject" size="sm" variant="ghost">Reject</x-ui.button><x-ui.button name="decision" value="approve" size="sm">Approve</x-ui.button></form>
                    @elseif ($expense->status === \App\Enums\ExpenseStatus::Approved)
                        <x-ui.confirm :action="route('manage.expenses.paid', $expense)" title="Mark as paid" confirm="Mark paid" size="sm" variant="secondary">Mark paid<x-slot:fields><x-ui.input name="payment_reference" label="Payment reference" required /></x-slot:fields></x-ui.confirm>
                    @endif
                @endcan
            </div>
        @empty
            <x-ui.empty icon="wallet" title="No expenses recorded" />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $expenses->links() }}</div>

    <x-ui.modal name="expense" title="Record expense">
        <form method="POST" action="{{ route('manage.expenses.store') }}" class="space-y-4">@csrf
            <x-ui.input name="description" label="Description" required />
            <div class="grid grid-cols-2 gap-3"><x-ui.select name="category" label="Category" :options="$categories" /><x-ui.input name="amount" label="Amount" prefix="₦" required /></div>
            <div class="grid grid-cols-2 gap-3"><x-ui.select name="vendor_id" label="Vendor" :options="$vendors->pluck('name', 'id')->all()" placeholder="None" /><x-ui.input name="vendor_invoice_number" label="Vendor invoice #" optional /></div>
            <x-ui.input name="incurred_on" type="date" label="Date" :value="now()->toDateString()" required />
            <x-ui.button class="w-full">Submit for approval</x-ui.button>
        </form>
    </x-ui.modal>
    <x-ui.modal name="vendor" title="Add vendor">
        <form method="POST" action="{{ route('manage.vendors.store') }}" class="space-y-4">@csrf
            <x-ui.input name="name" label="Company" required />
            <div class="grid grid-cols-2 gap-3"><x-ui.input name="contact_name" label="Contact" optional /><x-ui.input name="phone" label="Phone" optional /></div>
            <x-ui.input name="email" type="email" label="Email" optional />
            <x-ui.input name="service_category" label="Service" optional placeholder="e.g. plumbing" />
            <x-ui.button class="w-full">Add vendor</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
