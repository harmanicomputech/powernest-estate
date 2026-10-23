<x-layouts.app title="Bookings">
    <x-ui.page-header title="Facility bookings">
        <x-ui.button type="button" variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'facility')">Add facility</x-ui.button>
    </x-ui.page-header>
    <x-ui.card title="Awaiting approval" :padded="false">
        @forelse ($pending as $booking)
            <div class="flex flex-wrap items-center gap-3 border-t border-line px-5 py-3.5 sm:px-6">
                <div class="min-w-0 flex-1"><p class="font-semibold">{{ $booking->facility->name }} · {{ local_time($booking->starts_at, 'D j M, g:ia') }}–{{ local_time($booking->ends_at, 'g:ia') }}</p><p class="text-sm text-muted">{{ $booking->user->name }} ({{ $booking->property?->identifier }}) · {{ $booking->guests }} guests{{ $booking->purpose ? ' · '.$booking->purpose : '' }}</p></div>
                <form method="POST" action="{{ route('manage.bookings.review', $booking) }}" class="flex gap-1.5">@csrf<x-ui.button name="decision" value="reject" size="sm" variant="ghost">Decline</x-ui.button><x-ui.button name="decision" value="approve" size="sm">Approve</x-ui.button></form>
            </div>
        @empty
            <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">No bookings awaiting approval.</p>
        @endforelse
    </x-ui.card>
    <x-ui.card title="Upcoming confirmed" class="mt-6" :padded="false">
        @forelse ($upcoming as $booking)
            <div class="flex items-center justify-between border-t border-line px-5 py-3 text-sm sm:px-6"><span><span class="font-semibold">{{ $booking->facility->name }}</span> · {{ $booking->user->name }}</span><span class="text-muted">{{ local_time($booking->starts_at, 'D j M, g:ia') }}</span></div>
        @empty
            <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">Nothing booked.</p>
        @endforelse
    </x-ui.card>
    <x-ui.modal name="facility" title="Add facility">
        <form method="POST" action="{{ route('manage.facilities.store') }}" class="space-y-4">@csrf
            <x-ui.input name="name" label="Name" required />
            <x-ui.textarea name="description" label="Description" rows="2" optional />
            <div class="grid grid-cols-2 gap-3"><x-ui.input name="opens_at" type="time" label="Opens" value="08:00" /><x-ui.input name="closes_at" type="time" label="Closes" value="21:00" /></div>
            <div class="grid grid-cols-3 gap-3"><x-ui.input name="capacity" type="number" label="Capacity" optional /><x-ui.input name="max_duration_minutes" type="number" label="Max mins" value="120" /><x-ui.input name="fee" label="Fee" prefix="₦" optional /></div>
            <x-ui.checkbox name="requires_approval" label="Bookings need approval" checked />
            <x-ui.textarea name="rules" label="Rules" rows="2" optional />
            <x-ui.button class="w-full">Add facility</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
