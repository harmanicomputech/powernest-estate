<?php

namespace App\Notifications;

use App\Models\Visit;

class WalkInApprovalNotification extends AppNotification
{
    protected string $category = 'visitors';

    protected bool $essential = true;

    public function __construct(public Visit $visit) {}

    protected function title(): string
    {
        return "Visitor at the gate: {$this->visit->visitor_name}";
    }

    protected function body(): string
    {
        return "Security is asking whether to let {$this->visit->visitor_name} in to {$this->visit->property->identifier}. Approve or decline in the app.";
    }

    protected function url(): ?string
    {
        return route('visitors.walk-ins.show', $this->visit);
    }

    protected function icon(): string
    {
        return 'shield';
    }

    protected function estateId(): ?int
    {
        return $this->visit->estate_id;
    }
}
