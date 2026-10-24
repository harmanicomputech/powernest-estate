@php use App\Enums\PowerPurchaseStatus as S; @endphp
<x-layouts.app title="Power operations" wide>
    <x-ui.page-header title="Power operations" description="Electricity sales, in-flight purchases and anything needing reconciliation.">
        @can('reports.view')<x-ui.button :href="route('manage.reports.index', ['type' => 'power']).'#downloads'" variant="secondary" icon="download">Download report</x-ui.button>@endcan
        @can('power.vend_manual')<x-ui.button :href="route('manage.office-vend.create')" icon="zap">Issue token</x-ui.button>@endcan
    </x-ui.page-header>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Sales today" :value="money($todayVolume, false)" icon="zap" tone="gold" />
        <x-ui.stat label="Sales this month" :value="money($monthVolume, false)" icon="trending-up" :hint="'Service charges '.money($monthFees, false)" />
        <x-ui.stat label="Needs attention" :value="($counts[S::VendFailed->value] ?? 0) + ($counts[S::ReconciliationRequired->value] ?? 0)" icon="triangle-alert" tone="danger" :href="route('manage.power.index')" />
        <x-ui.stat label="In flight" :value="($counts[S::Paid->value] ?? 0) + ($counts[S::Vending->value] ?? 0)" icon="loader" tone="info" :href="route('manage.power.index', ['status' => 'in_flight'])" />
    </div>

    @if (array_sum($trend['values']) > 0)
        <x-ui.card title="Daily sales (₦)" class="mt-6">
            <div class="h-48" x-data="chart(@js(['type' => 'bar', 'data' => ['labels' => $trend['labels'], 'datasets' => [['data' => $trend['values'], 'backgroundColor' => '#d4a843', 'borderRadius' => 6, 'maxBarThickness' => 26]]], 'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true]]]]))"><canvas x-ref="canvas" role="img" aria-label="Daily electricity sales"></canvas></div>
        </x-ui.card>
    @endif

    <div class="mt-8">
        <x-ui.tabs :active="$status" :tabs="[
            'attention' => ['Needs attention', route('manage.power.index'), ($counts[S::VendFailed->value] ?? 0) + ($counts[S::ReconciliationRequired->value] ?? 0)],
            'in_flight' => ['In flight', route('manage.power.index', ['status' => 'in_flight'])],
            'pending' => ['Awaiting payment', route('manage.power.index', ['status' => 'pending']), $counts[S::PendingPayment->value] ?? 0],
            'issued' => ['Issued', route('manage.power.index', ['status' => 'issued'])],
            'all' => ['All', route('manage.power.index', ['status' => 'all'])],
        ]" />
        <form class="mb-4 flex gap-2"><input type="hidden" name="status" value="{{ $status }}"><div class="flex-1"><x-ui.input name="q" :value="$search" placeholder="Reference, meter or gateway reference" /></div><x-ui.button variant="secondary" icon="search">Search</x-ui.button></form>
        <x-ui.card :padded="false" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted"><tr><th class="px-5 py-3">Reference</th><th class="px-3 py-3">Customer</th><th class="px-3 py-3">Meter</th><th class="px-3 py-3 text-right">Amount</th><th class="px-3 py-3">Gateway</th><th class="px-3 py-3">Created</th><th class="px-5 py-3 text-right">Status</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($purchases as $purchase)
                            <tr class="cursor-pointer hover:bg-canvas" onclick="location.href='{{ route('manage.power.show', $purchase) }}'">
                                <td class="px-5 py-3 font-mono text-xs font-semibold"><a href="{{ route('manage.power.show', $purchase) }}">{{ $purchase->reference }}</a></td>
                                <td class="px-3 py-3">{{ $purchase->user->name }}<p class="text-xs text-muted">{{ $purchase->property->identifier }}</p></td>
                                <td class="px-3 py-3 font-mono text-xs">{{ $purchase->meter_number }}</td>
                                <td class="px-3 py-3 text-right font-semibold tnum">{{ money($purchase->vend_amount) }}</td>
                                <td class="px-3 py-3">{{ ucfirst($purchase->gateway) }}</td>
                                <td class="px-3 py-3 text-muted">{{ local_time($purchase->created_at, 'j M, g:ia') }}</td>
                                <td class="px-5 py-3 text-right"><x-ui.status :value="$purchase->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-ui.empty icon="circle-check" title="Nothing here" :description="$status === 'attention' ? 'No purchases need reconciliation.' : null" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
        <div class="mt-4">{{ $purchases->links() }}</div>
    </div>
</x-layouts.app>
