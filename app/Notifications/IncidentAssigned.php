<?php

namespace App\Notifications;

use App\Models\Incident;

class IncidentAssigned extends AppNotification
{
    protected string $category = 'operations';

    protected bool $essential = true;

    public function __construct(public Incident $incident) {}

    protected function title(): string
    {
        return "Incident {$this->incident->reference} assigned to you";
    }

    protected function body(): string
    {
        return "{$this->incident->severity->label()} · {$this->incident->title}";
    }

    protected function url(): ?string
    {
        return route('incidents.show', $this->incident);
    }

    protected function icon(): string
    {
        return 'siren';
    }

    protected function estateId(): ?int
    {
        return $this->incident->estate_id;
    }
}
