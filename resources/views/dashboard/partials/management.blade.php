<section class="space-y-6" aria-label="Estate overview">
    <h2 class="text-lg font-extrabold">Estate overview</h2>

    @php
        $alerts = array_filter([
            $data['pendingApprovals'] ? ['key-round', "{$data['pendingApprovals']} access request(s) awaiting approval", route('manage.members.index', ['tab' => 'requests'])] : null,
            $data['powerAttention'] ? ['triangle-alert', "{$data['powerAttention']} electricity purchase(s) need reconciliation", route('manage.power.index')] : null,
            $data['overdueInvoices'] ? ['receipt', "{$data['overdueInvoices']} overdue invoice(s)", route('manage.billing.invoices', ['status' => 'overdue'])] : null,
            $data['pendingBookings'] ? ['calendar-check', "{$data['pendingBookings']} booking(s) awaiting approval", route('manage.bookings.index')] : null,
        ]);
    @endphp
    @if ($alerts)
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($alerts as [$icon, $text, $url])
                <a href="{{ $url }}" class="flex items-center gap-3 rounded-xl border border-line bg-surface px-4 py-3 text-sm font-semibold transition hover:border-line-strong">
                    <span class="grid size-8 place-items-center rounded-lg bg-warning-soft text-warning"><x-icon :name="$icon" class="size-4" /></span>
                    <span class="flex-1">{{ $text }}</span>
                    <x-icon name="chevron-right" class="size-4 text-faint" />
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Properties" :value="number_format($data['properties'])" icon="building-2" :hint="$data['occupancy'] !== null ? $data['occupancy'].'% occupied' : 'No properties yet'" />
        <x-ui.stat label="Residents" :value="number_format($data['residents'])" icon="users" tone="info" :hint="$data['pendingApprovals'] ? $data['pendingApprovals'].' pending' : 'No pending requests'" />
        <x-ui.stat label="Visitors today" :value="number_format($data['visitorsToday'])" icon="door-open" tone="gold" />
        <x-ui.stat label="Open issues" :value="number_format($data['openMaintenance'] + $data['openIncidents'])" icon="wrench" tone="warning" :hint="$data['openMaintenance'].' maintenance · '.$data['openIncidents'].' incidents'" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Collections this month" class="lg:col-span-1">
            @php $rate = $data['billedThisMonth'] > 0 ? min(100, (int) round($data['collectedThisMonth'] / $data['billedThisMonth'] * 100)) : null; @endphp
            <p class="text-[1.75rem] font-extrabold tracking-tight tnum">{{ money($data['collectedThisMonth'], false) }}</p>
            <p class="text-sm text-muted">of {{ money($data['billedThisMonth'], false) }} billed</p>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-sunken" role="progressbar" aria-valuenow="{{ $rate ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-brand-500 transition-all" style="width: {{ $rate ?? 0 }}%"></div>
            </div>
            <dl class="mt-5 space-y-2.5 text-sm">
                <div class="flex justify-between"><dt class="text-muted">Collection rate</dt><dd class="font-bold tnum">{{ $rate !== null ? $rate.'%' : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Total outstanding</dt><dd class="font-bold tnum">{{ money($data['outstanding'], false) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Overdue invoices</dt><dd class="font-bold tnum">{{ $data['overdueInvoices'] }}</dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Electricity sales" description="Tokens issued, last 14 days (₦)" class="lg:col-span-2">
            <x-slot:action><span class="text-right"><span class="block text-lg font-extrabold tnum">{{ money($data['powerToday'], false) }}</span><span class="text-xs text-muted">today · {{ $data['powerCountToday'] }} tokens</span></span></x-slot:action>
            @if (array_sum($data['powerTrend']['values']) > 0)
                <div class="h-52" x-data="chart(@js(['type' => 'line', 'data' => ['labels' => $data['powerTrend']['labels'], 'datasets' => [['data' => $data['powerTrend']['values'], 'borderColor' => '#1f6e4e', 'backgroundColor' => 'rgba(47,136,98,0.10)', 'fill' => true, 'tension' => 0.35, 'pointRadius' => 0, 'borderWidth' => 2]]], 'options' => ['maintainAspectRatio' => false, 'interaction' => ['intersect' => false, 'mode' => 'index'], 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 7]], 'y' => ['beginAtZero' => true]]]]))">
                    <canvas x-ref="canvas" role="img" aria-label="Electricity sales trend"></canvas>
                </div>
            @else
                <x-ui.empty icon="zap" title="No tokens issued in this period" description="Sales will chart here once residents start buying electricity." class="!py-8" />
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Recent activity" :padded="false">
        @can('audit.view')<x-slot:action><a href="{{ route('manage.audit.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Audit log</a></x-slot:action>@endcan
        <ul class="divide-y divide-line border-t border-line">
            @forelse ($data['activity'] as $log)
                <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                    <x-ui.avatar :name="$log->user?->name ?? 'System'" size="sm" />
                    <p class="min-w-0 flex-1 truncate text-sm"><span class="font-semibold">{{ $log->user?->name ?? 'System' }}</span> <span class="text-muted">{{ str_replace(['.', '_'], [' · ', ' '], $log->action) }}</span></p>
                    <span class="shrink-0 text-xs text-faint">{{ $log->created_at->diffForHumans(short: true) }}</span>
                </li>
            @empty
                <li class="px-5 py-6 text-sm text-muted sm:px-6">No activity recorded yet.</li>
            @endforelse
        </ul>
    </x-ui.card>
</section>
