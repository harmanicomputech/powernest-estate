<x-layouts.app title="Visitors inside">
    <x-ui.page-header title="Visitors inside" description="Based on recorded check-ins without a check-out." :back="route('gate.index')" />
    <form class="mb-4"><x-ui.input name="q" :value="$search" placeholder="Search name or plate" prefix="⌕" /></form>
    <x-ui.card :padded="false">
        @forelse ($visits as $visit)
            <div class="flex items-center gap-4 px-5 py-3.5 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <x-ui.avatar :name="$visit->visitor_name" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $visit->visitor_name }} <span class="font-normal text-muted">→ {{ $visit->property?->identifier }}</span></p>
                    <p class="text-sm text-muted">In since {{ local_time($visit->checked_in_at, 'g:ia') }} ({{ $visit->checked_in_at->diffForHumans(short: true) }}){{ $visit->vehicle_plate ? ' · '.$visit->vehicle_plate : '' }}</p>
                </div>
                <form method="POST" action="{{ route('gate.check-out', $visit) }}">@csrf
                    <x-ui.button variant="secondary" size="sm" icon="log-out">Check out</x-ui.button>
                </form>
            </div>
        @empty
            <x-ui.empty icon="door-open" title="Nobody checked in" />
        @endforelse
    </x-ui.card>
</x-layouts.app>
