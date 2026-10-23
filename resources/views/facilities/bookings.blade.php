<x-layouts.app title="My bookings">
    <x-ui.page-header title="My bookings" :back="route('facilities.index')" />
    <x-ui.card :padded="false">
        @forelse ($bookings as $booking)
            <div class="flex items-center gap-4 px-5 py-4 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <div class="grid w-12 shrink-0 place-items-center rounded-xl border border-line py-1.5 text-center">
                    <span class="text-[0.625rem] font-bold uppercase text-danger">{{ local_time($booking->starts_at, 'M') }}</span>
                    <span class="text-lg font-extrabold leading-none tnum">{{ local_time($booking->starts_at, 'j') }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $booking->facility->name }}</p>
                    <p class="text-sm text-muted">{{ local_time($booking->starts_at, 'D g:ia') }} – {{ local_time($booking->ends_at, 'g:ia') }} · {{ $booking->guests }} guest(s){{ $booking->review_note ? ' · '.$booking->review_note : '' }}</p>
                </div>
                <x-ui.status :value="$booking->status" />
                @if ($booking->invoice?->isPayable() && auth()->user()->can('view', $booking->invoice))
                    <x-ui.button :href="route('bills.show', $booking->invoice)" size="sm" icon="receipt">Pay {{ money($booking->invoice->balance(), false) }}</x-ui.button>
                @endif
                @if (in_array($booking->status, [\App\Enums\BookingStatus::Pending, \App\Enums\BookingStatus::Approved]) && $booking->starts_at->isFuture())
                    <x-ui.confirm :action="route('bookings.cancel', $booking)" title="Cancel this booking?" confirm="Cancel booking" confirm-variant="danger" size="sm" variant="ghost">Cancel</x-ui.confirm>
                @endif
            </div>
        @empty
            <x-ui.empty icon="calendar" title="No bookings yet"><x-ui.button :href="route('facilities.index')">Browse facilities</x-ui.button></x-ui.empty>
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $bookings->links() }}</div>
</x-layouts.app>
