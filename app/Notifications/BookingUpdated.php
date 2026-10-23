<?php

namespace App\Notifications;

use App\Models\Booking;

class BookingUpdated extends AppNotification
{
    protected string $category = 'bookings';

    protected bool $essential = false;

    public function __construct(public Booking $booking) {}

    protected function title(): string
    {
        return "Booking {$this->booking->status->label()}: {$this->booking->facility->name}";
    }

    protected function body(): string
    {
        return $this->booking->starts_at->timezone($this->booking->estate->timezone())->format('D j M, g:ia').' – '.$this->booking->ends_at->timezone($this->booking->estate->timezone())->format('g:ia').'.';
    }

    protected function url(): ?string
    {
        return route('facilities.bookings.index');
    }

    protected function icon(): string
    {
        return 'calendar-check';
    }

    protected function estateId(): ?int
    {
        return $this->booking->estate_id;
    }
}
