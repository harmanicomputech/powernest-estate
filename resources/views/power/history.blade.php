<x-layouts.app title="Electricity history">
    <x-ui.page-header title="Purchase history" :back="route('power.index')" />
    <form class="mb-4 flex gap-2">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <div class="flex-1"><x-ui.input name="q" :value="$search" placeholder="Search reference or meter" /></div>
        <x-ui.button variant="secondary" icon="search">Search</x-ui.button>
    </form>
    <x-ui.tabs :active="$status ?? 'all'" :tabs="[
        'all' => ['All', route('power.history', ['q' => $search])],
        'success' => ['Successful', route('power.history', ['status' => 'success', 'q' => $search])],
        'pending' => ['In progress', route('power.history', ['status' => 'pending', 'q' => $search])],
        'issues' => ['Issues', route('power.history', ['status' => 'issues', 'q' => $search])],
    ]" />
    <x-ui.card :padded="false">
        @forelse ($purchases as $purchase)
            <x-ui.row-link :href="route('power.purchases.show', $purchase)" icon="zap" tone="gold" :class="$loop->first ? '' : 'border-t border-line'">
                <p class="font-semibold tnum">{{ money($purchase->vend_amount) }} <span class="font-normal text-muted">· {{ $purchase->meter?->label ?? $purchase->meter_number }}</span></p>
                <p class="truncate text-sm text-muted"><span class="font-mono">{{ $purchase->reference }}</span> · {{ local_time($purchase->created_at, 'j M Y, g:ia') }}</p>
                <x-slot:aside><x-ui.status :value="$purchase->status" /></x-slot:aside>
            </x-ui.row-link>
        @empty
            <x-ui.empty icon="history" title="No purchases found" />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $purchases->links() }}</div>
</x-layouts.app>
