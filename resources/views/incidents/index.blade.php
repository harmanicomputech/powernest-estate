<x-layouts.app title="Incidents">
    <x-ui.page-header title="Incidents" :description="$isManager ? 'All incident reports for the estate. Records are restricted to security management.' : 'Incidents you reported or were assigned.'">
        @can('incidents.report')<x-ui.button :href="route('incidents.create')" icon="siren">Report incident</x-ui.button>@endcan
    </x-ui.page-header>
    <x-ui.tabs :active="$tab" :tabs="['open' => ['Open', route('incidents.index')], 'closed' => ['Resolved', route('incidents.index', ['tab' => 'closed'])], 'all' => ['All', route('incidents.index', ['tab' => 'all'])]]" />
    <x-ui.card :padded="false">
        @forelse ($incidents as $incident)
            <a href="{{ route('incidents.show', $incident) }}" class="flex items-center gap-4 px-5 py-4 transition hover:bg-canvas sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl {{ ['neutral' => 'bg-sunken text-ink-soft', 'info' => 'bg-info-soft text-info', 'warning' => 'bg-warning-soft text-warning', 'danger' => 'bg-danger-soft text-danger'][$incident->severity->tone()] }}"><x-icon name="siren" class="size-[1.125rem]" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $incident->title }}</p>
                    <p class="truncate text-sm text-muted">{{ $incident->reference }} · {{ local_time($incident->occurred_at, 'j M, g:ia') }}{{ $incident->location ? ' · '.$incident->location : '' }}</p>
                </div>
                <x-ui.status :value="$incident->severity" />
                <x-ui.status :value="$incident->status" class="hidden sm:inline-flex" />
            </a>
        @empty
            <x-ui.empty icon="shield-check" title="No incidents" />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $incidents->links() }}</div>
</x-layouts.app>
