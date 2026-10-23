<?php

namespace App\Notifications;

use App\Models\PaymentSubmission;
use App\Support\Money;

class PaymentSubmissionReceived extends AppNotification
{
    protected string $category = 'operations';

    public function __construct(public PaymentSubmission $submission) {}

    protected function title(): string
    {
        return 'Payment receipt to verify';
    }

    protected function body(): string
    {
        return "{$this->submission->submitter->name} uploaded a ".Money::format($this->submission->amount)." receipt for {$this->submission->invoice->number} ({$this->submission->property->identifier}).";
    }

    protected function url(): ?string
    {
        return route('manage.billing.submissions.show', $this->submission);
    }

    protected function icon(): string
    {
        return 'file-check';
    }

    protected function estateId(): ?int
    {
        return $this->submission->estate_id;
    }
}
