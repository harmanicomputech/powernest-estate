<x-layouts.app :title="$facility->name">
    <x-ui.page-header :title="$facility->name" :description="$facility->description" :back="route('facilities.index')" />
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <x-ui.card :title="'Availability · '.$day->format('l j F')">
                <x-slot:action>
                    <form class="flex items-center gap-2"><input type="date" name="date" value="{{ $day->toDateString() }}" min="{{ now($tz)->toDateString() }}" class="control h-9 py-1 text-sm" onchange="this.form.submit()"></form>
                </x-slot:action>
                @php
                    $open = (int) substr($facility->opens_at, 0, 2); $close = (int) substr($facility->closes_at, 0, 2);
                @endphp
                <div class="space-y-1.5">
                    @for ($h = $open; $h < $close; $h++)
                        @php
                            $slotStart = $day->copy()->setTime($h, 0); $slotEnd = $slotStart->copy()->addHour();
                            $taken = $bookings->first(fn ($b) => $b->starts_at->lt($slotEnd->copy()->utc()) && $b->ends_at->gt($slotStart->copy()->utc()));
                        @endphp
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-14 shrink-0 text-right text-muted tnum">{{ $slotStart->format('g a') }}</span>
                            <span @class(['flex h-8 flex-1 items-center rounded-lg px-3 text-xs font-semibold', 'bg-sunken text-muted' => $taken, 'bg-brand-50 text-brand-700' => ! $taken])>{{ $taken ? ($taken->status->label() === 'Confirmed' ? 'Booked' : 'Requested') : 'Available' }}</span>
                        </div>
                    @endfor
                </div>
            </x-ui.card>
            @if ($facility->rules)
                <x-ui.card title="House rules"><p class="whitespace-pre-line text-sm text-ink-soft">{{ $facility->rules }}</p></x-ui.card>
            @endif
        </div>
        <div class="lg:col-span-2">
            <x-ui.card title="Book">
                <form method="POST" action="{{ route('facilities.book', $facility) }}" class="space-y-4" x-data="submitting" x-on:submit="submit">
                    @csrf
                    <x-ui.input name="date" type="date" label="Date" :value="$day->toDateString()" required />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="start_time" type="time" label="From" value="{{ substr($facility->opens_at, 0, 5) }}" required />
                        <x-ui.input name="end_time" type="time" label="Until" required />
                    </div>
                    <x-ui.input name="guests" type="number" label="Guests" value="1" min="1" :max="$facility->capacity" />
                    <x-ui.input name="purpose" label="Purpose" optional />
                    @error('starts_at')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror
                    <x-ui.dl :items="['Max duration' => intdiv($facility->max_duration_minutes, 60).'h '.($facility->max_duration_minutes % 60 ? ($facility->max_duration_minutes % 60).'m' : ''), 'Fee' => $facility->fee ? money($facility->fee) : 'Free', 'Approval' => $facility->requires_approval ? 'Required' : 'Instant']" />
                    <x-ui.button class="w-full" busy icon="calendar-check">{{ $facility->requires_approval ? 'Request booking' : 'Book now' }}</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
