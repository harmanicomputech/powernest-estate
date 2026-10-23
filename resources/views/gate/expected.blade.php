<x-layouts.app title="Expected visitors">
    <x-ui.page-header title="Expected today" description="Active passes valid at some point today." :back="route('gate.index')" />
    <x-ui.card :padded="false">
        @forelse ($passes as $pass)
            <div class="flex items-center gap-4 px-5 py-3.5 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <x-ui.avatar :name="$pass->visitor_name" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $pass->visitor_name }} <span class="font-normal text-muted">→ {{ $pass->property->identifier }}</span></p>
                    <p class="truncate text-sm text-muted">{{ humanize($pass->category) }} · from {{ local_time($pass->valid_from, 'g:ia') }} · host {{ $pass->host->name }}{{ $pass->vehicle_plate ? ' · '.$pass->vehicle_plate : '' }}</p>
                </div>
                <span class="text-sm font-semibold text-muted tnum">{{ $pass->entries_count }}{{ $pass->max_entries ? '/'.$pass->max_entries : '' }}</span>
            </div>
        @empty
            <x-ui.empty icon="clock" title="No visitors expected" description="Passes created by residents for today will show here." />
        @endforelse
    </x-ui.card>
</x-layouts.app>
