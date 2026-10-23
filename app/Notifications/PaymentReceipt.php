<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;

class PaymentReceipt extends AppNotification
{
    protected string $category = 'billing';

    protected bool $essential = true;

    public function __construct(public Payment $payment) {}

    protected function title(): string
    {
        return 'Payment received · '.Money::format($this->payment->amount_received ?? $this->payment->amount);
    }

    protected function body(): string
    {
        return "Thank you. Payment {$this->payment->reference} was applied to ".($this->payment->invoice?->number ?? 'your account').'.';
    }

    protected function url(): ?string
    {
        return route('bills.receipt', $this->payment);
    }

    protected function icon(): string
    {
        return 'circle-check';
    }

    protected function estateId(): ?int
    {
        return $this->payment->estate_id;
    }
}
