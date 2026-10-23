<section class="space-y-6" aria-label="Security">
    <div class="flex items-end justify-between">
        <h2 class="text-lg font-extrabold">Gate today</h2>
        <x-ui.button :href="route('gate.index')" icon="scan-line">Open gate console</x-ui.button>
    </div>

    @if ($data['awaiting']->isNotEmpty())
        <x-ui.alert tone="warning" title="{{ $data['awaiting']->count() }} visitor(s) awaiting host approval">
            @foreach ($data['awaiting'] as $visit){{ $visit->visitor_name }} → {{ $visit->property?->identifier }}@if(! $loop->last), @endif @endforeach
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Expected today" :value="$data['expectedToday']" icon="clock" :href="route('gate.expected')" />
        <x-ui.stat label="Inside now" :value="$data['inside']" icon="door-open" tone="info" :href="route('gate.inside')" hint="From recorded check-ins" />
        <x-ui.stat label="Entries today" :value="$data['entriesToday']" icon="log-in" tone="gold" />
        <x-ui.stat label="Denied today" :value="$data['deniedToday']" icon="shield" tone="danger" />
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <x-ui.card title="Entries by hour" description="Check-ins recorded today" class="lg:col-span-3">
            @if (array_sum($data['hourly']['values']) > 0)
                <div class="h-56" x-data="chart(@js(['type' => 'bar', 'data' => ['labels' => $data['hourly']['labels'], 'datasets' => [['data' => $data['hourly']['values'], 'backgroundColor' => '#2f8862', 'borderRadius' => 6, 'maxBarThickness' => 22]]], 'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['grid' => ['display' => false]], 'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]]]))">
                    <canvas x-ref="canvas" aria-label="Entries by hour chart" role="img"></canvas>
                </div>
            @else
                <x-ui.empty icon="chart-column" title="No entries yet today" description="The chart fills in as visitors are checked in." class="!py-8" />
            @endif
        </x-ui.card>
        <x-ui.card title="Active incidents" :padded="false" class="lg:col-span-2">
            <x-slot:action><a href="{{ route('incidents.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">All</a></x-slot:action>
            @forelse ($data['activeIncidents'] as $incident)
                <x-ui.row-link :href="route('incidents.show', $incident)" icon="siren" :tone="$incident->severity->tone()" class="border-t border-line">
                    <p class="truncate text-sm font-semibold">{{ $incident->title }}</p>
                    <p class="text-xs text-muted">{{ $incident->reference }} · {{ $incident->occurred_at->diffForHumans() }}</p>
                </x-ui.row-link>
            @empty
                <x-ui.empty icon="shield-check" title="No active incidents" class="border-t border-line !py-8" />
            @endforelse
        </x-ui.card>
    </div>
</section>
