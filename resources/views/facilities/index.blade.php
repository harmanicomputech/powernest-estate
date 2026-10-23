<x-layouts.app title="Facilities">
    <x-ui.page-header title="Facilities" description="Book shared spaces in the estate.">
        <x-ui.button :href="route('facilities.bookings.index')" variant="secondary" icon="calendar-check">My bookings</x-ui.button>
    </x-ui.page-header>

    @if ($upcoming->isNotEmpty())
        <div class="scroll-x -mx-4 mb-6 flex gap-3 px-4 sm:mx-0 sm:px-0">
            @foreach ($upcoming as $booking)
                <div class="card min-w-[15rem] p-4">
                    <div class="flex items-center justify-between"><p class="text-sm font-bold">{{ $booking->facility->name }}</p><x-ui.status :value="$booking->status" /></div>
                    <p class="mt-1 text-sm text-muted">{{ local_time($booking->starts_at, 'D j M · g:ia') }} – {{ local_time($booking->ends_at, 'g:ia') }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($facilities as $facility)
            <a href="{{ route('facilities.show', $facility) }}" class="card group flex flex-col overflow-hidden transition hover:shadow-[var(--shadow-raised)]">
                <div class="relative grid h-32 place-items-center overflow-hidden bg-gradient-to-br from-brand-800 to-brand-950">
                    <x-icon :name="str_contains(strtolower($facility->name), 'pool') ? 'waves' : (str_contains(strtolower($facility->name), 'tennis') ? 'trophy' : 'building')" class="size-10 text-gold-200/90 transition group-hover:scale-110" />
                </div>
                <div class="flex flex-1 flex-col p-5">
                    <p class="font-bold">{{ $facility->name }}</p>
                    <p class="mt-1 line-clamp-2 flex-1 text-sm text-muted">{{ $facility->description }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <x-ui.badge>{{ substr($facility->opens_at, 0, 5) }}–{{ substr($facility->closes_at, 0, 5) }}</x-ui.badge>
                        @if ($facility->capacity)<x-ui.badge>Up to {{ $facility->capacity }}</x-ui.badge>@endif
                        <x-ui.badge :tone="$facility->fee ? 'gold' : 'success'">{{ $facility->fee ? money($facility->fee, false) : 'Free' }}</x-ui.badge>
                        @if ($facility->requires_approval)<x-ui.badge tone="info">Needs approval</x-ui.badge>@endif
                    </div>
                </div>
            </a>
        @empty
            <x-ui.card class="sm:col-span-2 lg:col-span-3"><x-ui.empty icon="calendar" title="No facilities listed" description="The estate hasn't added bookable facilities yet." /></x-ui.card>
        @endforelse
    </div>
</x-layouts.app>
