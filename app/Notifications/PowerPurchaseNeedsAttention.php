<?php

namespace App\Notifications;

use App\Models\PowerPurchase;

class PowerPurchaseNeedsAttention extends AppNotification
{
    protected string $category = 'operations';

    protected bool $essential = true;

    public function __construct(public PowerPurchase $purchase) {}

    protected function title(): string
    {
        return 'Electricity purchase needs attention';
    }

    protected function body(): string
    {
        return "{$this->purchase->reference} ({$this->purchase->meter_number}) is {$this->purchase->status->label()}: ".($this->purchase->failure_reason ?? 'review required').'.';
    }

    protected function url(): ?string
    {
        return route('manage.power.show', $this->purchase);
    }

    protected function icon(): string
    {
        return 'triangle-alert';
    }

    protected function estateId(): ?int
    {
        return $this->purchase->estate_id;
    }
}
