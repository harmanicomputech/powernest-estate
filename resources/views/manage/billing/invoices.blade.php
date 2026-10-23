<x-layouts.app title="Invoices" wide>
    <x-ui.page-header title="Invoices" :back="route('manage.billing.index')"><x-ui.button :href="route('manage.billing.invoices.create')" icon="plus">New invoice</x-ui.button></x-ui.page-header>
    <x-ui.tabs :active="$status" :tabs="['outstanding' => ['Outstanding', route('manage.billing.invoices')], 'overdue' => ['Overdue', route('manage.billing.invoices', ['status' => 'overdue'])], 'paid' => ['Paid', route('manage.billing.invoices', ['status' => 'paid'])], 'void' => ['Void', route('manage.billing.invoices', ['status' => 'void'])], 'all' => ['All', route('manage.billing.invoices', ['status' => 'all'])]]" />
    <form class="mb-4 flex gap-2"><input type="hidden" name="status" value="{{ $status }}"><div class="flex-1"><x-ui.input name="q" :value="$search" placeholder="Invoice number or unit" /></div><x-ui.button variant="secondary" icon="search">Search</x-ui.button></form>
    <x-ui.card :padded="false" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-sm">
                <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted"><tr><th class="px-5 py-3">Invoice</th><th class="px-3 py-3">Unit</th><th class="px-3 py-3">Billed to</th><th class="px-3 py-3">Due</th><th class="px-3 py-3 text-right">Total</th><th class="px-3 py-3 text-right">Balance</th><th class="px-5 py-3 text-right">Status</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($invoices as $invoice)
                        <tr class="cursor-pointer hover:bg-canvas" onclick="location.href='{{ route('bills.show', $invoice) }}'">
                            <td class="px-5 py-3"><a href="{{ route('bills.show', $invoice) }}" class="font-semibold">{{ $invoice->number }}</a><p class="text-xs text-muted">{{ $invoice->description }}</p></td>
                            <td class="px-3 py-3 font-semibold">{{ $invoice->property->identifier }}</td>
                            <td class="px-3 py-3">{{ $invoice->billedUser?->name ?? '—' }}</td>
                            <td @class(['px-3 py-3', 'font-semibold text-danger' => $invoice->isOverdue()])>{{ $invoice->due_date->format('j M Y') }}</td>
                            <td class="px-3 py-3 text-right tnum">{{ money($invoice->total) }}</td>
                            <td class="px-3 py-3 text-right font-semibold tnum">{{ money($invoice->balance()) }}</td>
                            <td class="px-5 py-3 text-right"><x-ui.status :value="$invoice->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty icon="receipt" title="No invoices" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
