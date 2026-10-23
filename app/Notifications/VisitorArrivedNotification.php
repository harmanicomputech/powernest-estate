<?php

namespace App\Notifications;

use App\Models\Visit;

class VisitorArrivedNotification extends AppNotification
{
    protected string $category = 'visitors';

    protected bool $essential = false;

    public function __construct(public Visit $visit) {}

    protected function title(): string
    {
        return "{$this->visit->visitor_name} has arrived";
    }

    protected function body(): string
    {
        return "Checked in at the gate at ".$this->visit->checked_in_at->timezone($this->visit->estate->timezone())->format("g:ia").($this->visit->vehicle_plate ? " · Vehicle {$this->visit->vehicle_plate}" : "").".";
    }

    protected function url(): ?string
    {
        return route('visitors.index', ['tab' => 'history']);
    }

    protected function icon(): string
    {
        return 'door-open';
    }

    protected function estateId(): ?int
    {
        return $this->visit->estate_id;
    }
}
