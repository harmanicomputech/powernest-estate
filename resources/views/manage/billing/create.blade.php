<x-layouts.app title="New invoice">
    <x-ui.page-header title="New invoice" :back="route('manage.billing.index')" />
    <form method="POST" action="{{ route('manage.billing.invoices.store') }}" class="mx-auto max-w-3xl" x-data="{ items: @js(old('items', [['description' => '', 'quantity' => 1, 'unit_amount' => '']])) }">
        @csrf
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-ui.select name="property_id" label="Property" :options="$properties->pluck('identifier', 'id')->all()" :value="$selected" placeholder="Choose" required />
                <x-ui.input name="description" label="Description" required class="sm:col-span-1" placeholder="e.g. Gate repair levy" />
                <x-ui.input name="due_date" type="date" label="Due date" :value="now()->addDays(14)->toDateString()" required />
            </div>
            <p class="mb-2 mt-6 text-[0.8125rem] font-semibold text-ink-soft">Line items</p>
            <div class="space-y-2">
                <template x-for="(item, i) in items" :key="i">
                    <div class="grid grid-cols-12 gap-2">
                        <input class="control col-span-6" :name="`items[${i}][description]`" x-model="item.description" placeholder="Item" required>
                        <input class="control col-span-2 text-center" type="number" min="1" :name="`items[${i}][quantity]`" x-model="item.quantity" required>
                        <input class="control col-span-3 text-right tnum" inputmode="decimal" :name="`items[${i}][unit_amount]`" x-model="item.unit_amount" placeholder="₦0.00" required>
                        <button type="button" class="col-span-1 grid place-items-center rounded-lg text-muted hover:bg-sunken" x-on:click="items.length > 1 && items.splice(i, 1)" aria-label="Remove line"><x-icon name="x" class="size-4" /></button>
                    </div>
                </template>
            </div>
            @if ($errors->has('items.*'))<p class="mt-2 text-sm text-danger">{{ $errors->first('items.*') }}</p>@endif
            <button type="button" class="mt-3 text-sm font-semibold text-brand-700" x-on:click="items.push({ description: '', quantity: 1, unit_amount: '' })">+ Add line</button>
            <x-ui.button class="mt-6 w-full" icon="receipt">Issue invoice</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
