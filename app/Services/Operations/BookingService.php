<?php

namespace App\Services\Operations;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use App\Notifications\BookingUpdated;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private AuditLogger $audit, private BillingService $billing) {}

    public function book(User $user, Facility $facility, Carbon $startsAt, Carbon $endsAt, int $guests, ?string $purpose): Booking
    {
        $estate = $facility->estate;
        $tz = $estate->timezone();
        $property = $user->authorizedProperties($estate)->first();

        if (! $facility->is_active) {
            throw ValidationException::withMessages(['facility' => 'This facility is not currently available.']);
        }

        if (! $property) {
            throw ValidationException::withMessages(['facility' => 'Only residents linked to a property can book facilities.']);
        }

        $localStart = $startsAt->copy()->timezone($tz);
        $localEnd = $endsAt->copy()->timezone($tz);

        $errors = match (true) {
            $endsAt->lte($startsAt) => 'The booking must end after it starts.',
            $startsAt->lt(now()) => 'Choose a time in the future.',
            $startsAt->gt(now()->addDays($facility->advance_booking_days)) => "Bookings open {$facility->advance_booking_days} days in advance.",
            $startsAt->diffInMinutes($endsAt) > $facility->max_duration_minutes => 'Bookings can last at most '.intdiv($facility->max_duration_minutes, 60).' hours.',
            ! $localStart->isSameDay($localEnd) => 'Bookings must start and end on the same day.',
            $localStart->format('H:i') < substr($facility->opens_at, 0, 5) || $localEnd->format('H:i') > substr($facility->closes_at, 0, 5) => 'Bookings must be within opening hours ('.substr($facility->opens_at, 0, 5).'–'.substr($facility->closes_at, 0, 5).').',
            $facility->capacity && $guests > $facility->capacity => "This facility holds at most {$facility->capacity} people.",
            default => null,
        };

        if ($errors) {
            throw ValidationException::withMessages(['starts_at' => $errors]);
        }

        $booking = DB::transaction(function () use ($user, $facility, $startsAt, $endsAt, $guests, $purpose, $property) {
            // Lock the facility so two overlapping requests cannot both pass the availability check.
            Facility::whereKey($facility->id)->lockForUpdate()->first();

            $clash = Booking::where('facility_id', $facility->id)
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Approved->value])
                ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages(['starts_at' => 'That time overlaps an existing booking. Choose another slot.']);
            }

            return Booking::create([
                'estate_id' => $facility->estate_id,
                'facility_id' => $facility->id,
                'user_id' => $user->id,
                'property_id' => $property->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'guests' => $guests,
                'purpose' => $purpose,
                'fee' => $facility->fee,
                'status' => $facility->requires_approval ? BookingStatus::Pending : BookingStatus::Approved,
            ]);
        });

        $this->audit->record('booking.created', $booking, ['facility' => $facility->name], $facility->estate_id, $user);

        if ($booking->status === BookingStatus::Approved) {
            $this->billFee($booking, $user);
        }

        if ($booking->status === BookingStatus::Approved) {
            $user->notify(new BookingUpdated($booking));
        }

        return $booking;
    }

    public function review(Booking $booking, User $reviewer, bool $approve, ?string $note): void
    {
        if ($booking->status !== BookingStatus::Pending) {
            throw ValidationException::withMessages(['booking' => 'This booking has already been reviewed.']);
        }

        $booking->update(['status' => $approve ? BookingStatus::Approved : BookingStatus::Rejected, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);

        if ($approve) {
            $this->billFee($booking, $reviewer);
        }
        $this->audit->record($approve ? 'booking.approved' : 'booking.rejected', $booking, [], $booking->estate_id, $reviewer);
        $booking->user->notify(new BookingUpdated($booking));
    }

    public function cancel(Booking $booking, User $actor): void
    {
        if (! in_array($booking->status, [BookingStatus::Pending, BookingStatus::Approved], true) || $booking->starts_at->isPast()) {
            throw ValidationException::withMessages(['booking' => 'This booking can no longer be cancelled.']);
        }

        $booking->update(['status' => BookingStatus::Cancelled]);

        if ($booking->invoice && $booking->invoice->amount_paid === 0 && $booking->invoice->status !== InvoiceStatus::Void) {
            $this->billing->void($booking->invoice, $actor, 'Booking cancelled');
        }
        $this->audit->record('booking.cancelled', $booking, [], $booking->estate_id, $actor);
    }

    /**
     * Facility fees are billed as a normal estate invoice on the booker's property, so they are
     * paid, receipted and reconciled through billing like any other charge.
     */
    private function billFee(Booking $booking, User $actor): void
    {
        if ($booking->fee <= 0 || $booking->invoice_id || ! $booking->property) {
            return;
        }

        $tz = $booking->estate->timezone();
        $invoice = $this->billing->issueInvoice(
            $booking->estate,
            $booking->property,
            [['description' => "{$booking->facility->name} · ".$booking->starts_at->timezone($tz)->format('D j M, g:ia'), 'quantity' => 1, 'unit_amount' => $booking->fee]],
            "Facility booking: {$booking->facility->name}",
            Carbon::parse($booking->starts_at->timezone($tz)->toDateString())->max(now()->startOfDay()),
            $actor,
        );

        $booking->update(['invoice_id' => $invoice->id]);
    }
}
