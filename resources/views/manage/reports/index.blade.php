<x-layouts.app title="Reports" wide>
    <x-ui.page-header title="Monthly report" :description="$start->format('F Y').' · generated from live records'">
        <form class="flex gap-2"><input type="month" name="month" value="{{ $month }}" class="control h-11" onchange="this.form.submit()"></form>
        <x-ui.button type="button" variant="secondary" icon="download" onclick="window.print()">Print</x-ui.button>
    </x-ui.page-header>

    <h2 class="mb-3 text-[0.9375rem] font-bold">Estate finances</h2>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Billed" :value="money($finance['billed'], false)" icon="receipt" />
        <x-ui.stat label="Collected" :value="money($finance['collected'], false)" icon="banknote" tone="gold" :hint="$finance['billed'] ? round($finance['collected'] / $finance['billed'] * 100).'% of billed' : null" />
        <x-ui.stat label="Expenses" :value="money($finance['expenses'], false)" icon="wallet" tone="warning" />
        <x-ui.stat label="Net (collected − expenses)" :value="money($finance['collected'] - $finance['expenses'], false)" icon="trending-up" tone="info" />
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Last 6 months (₦)" class="lg:col-span-2">
            <div class="h-64" x-data="chart(@js(['type' => 'bar', 'data' => ['labels' => $series->pluck('label'), 'datasets' => [['label' => 'Billed', 'data' => $series->pluck('billed'), 'backgroundColor' => '#b9dfca', 'borderRadius' => 5], ['label' => 'Collected', 'data' => $series->pluck('collected'), 'backgroundColor' => '#1f6e4e', 'borderRadius' => 5], ['label' => 'Expenses', 'data' => $series->pluck('expenses'), 'backgroundColor' => '#d4a843', 'borderRadius' => 5]]], 'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 10, 'usePointStyle' => true]]], 'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true]]]]))"><canvas x-ref="canvas" role="img" aria-label="Billing, collections and expenses over six months"></canvas></div>
        </x-ui.card>
        <x-ui.card title="Expenses by category">
            @forelse ($finance['expensesByCategory'] as $category => $total)
                <div class="mb-3">
                    <div class="flex justify-between text-sm"><span>{{ \App\Http\Controllers\Manage\ExpenseController::CATEGORIES[$category] ?? $category }}</span><span class="font-semibold tnum">{{ money((int) $total, false) }}</span></div>
                    <div class="mt-1 h-1.5 rounded-full bg-sunken"><div class="h-full rounded-full bg-gold-400" style="width: {{ $finance['expenses'] ? round($total / $finance['expenses'] * 100) : 0 }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-muted">No approved expenses this month.</p>
            @endforelse
        </x-ui.card>
    </div>

    <h2 class="mb-3 mt-10 text-[0.9375rem] font-bold">PowerNest electricity</h2>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Value vended" :value="money($power['volume'], false)" icon="zap" tone="gold" />
        <x-ui.stat label="Tokens issued" :value="number_format($power['count'])" icon="key-round" />
        <x-ui.stat label="Service charges" :value="money($power['fees'], false)" icon="banknote" />
        <x-ui.stat label="Failed / unresolved" :value="$power['failed']" icon="triangle-alert" tone="danger" />
    </div>

    <h2 class="mb-3 mt-10 text-[0.9375rem] font-bold">Operations</h2>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Visitor entries" :value="number_format($operations['visits'])" icon="door-open" :hint="$operations['denied'].' denied'" />
        <x-ui.stat label="Maintenance opened" :value="$operations['maintenanceOpened']" icon="wrench" tone="info" />
        <x-ui.stat label="Maintenance completed" :value="$operations['maintenanceClosed']" icon="circle-check" />
        <x-ui.stat label="Incidents" :value="$operations['incidents']" icon="siren" tone="warning" :hint="collect($operations['incidentsBySeverity'])->map(fn ($c, $s) => $c.' '.$s)->join(' · ') ?: null" />
    </div>
</x-layouts.app>
