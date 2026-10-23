<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;

class MaintenanceUpdated extends AppNotification
{
    protected string $category = 'maintenance';

    protected bool $essential = false;

    public function __construct(public MaintenanceRequest $request, public string $message) {}

    protected function title(): string
    {
        return "{$this->request->reference}: {$this->request->status->label()}";
    }

    protected function body(): string
    {
        return $this->message;
    }

    protected function url(): ?string
    {
        return route('maintenance.show', $this->request);
    }

    protected function icon(): string
    {
        return 'wrench';
    }

    protected function estateId(): ?int
    {
        return $this->request->estate_id;
    }
}
