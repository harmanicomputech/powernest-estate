<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Money;

class InvoiceReminder extends AppNotification
{
    protected string $category = 'billing';

    protected bool $essential = false;

    public function __construct(public Invoice $invoice) {}

    protected function title(): string
    {
        return "Reminder: {$this->invoice->number} is due ".$this->invoice->due_date->diffForHumans();
    }

    protected function body(): string
    {
        return Money::format($this->invoice->balance()).' outstanding for '.$this->invoice->description.'.';
    }

    protected function url(): ?string
    {
        return route('bills.show', $this->invoice);
    }

    protected function icon(): string
    {
        return 'receipt';
    }

    protected function estateId(): ?int
    {
        return $this->invoice->estate_id;
    }
}
