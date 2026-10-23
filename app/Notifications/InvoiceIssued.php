<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Money;

class InvoiceIssued extends AppNotification
{
    protected string $category = 'billing';

    protected bool $essential = false;

    public function __construct(public Invoice $invoice) {}

    protected function title(): string
    {
        return "New invoice {$this->invoice->number}";
    }

    protected function body(): string
    {
        return $this->invoice->description." · ".Money::format($this->invoice->total)." due ".$this->invoice->due_date->format("j M Y").".";
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
