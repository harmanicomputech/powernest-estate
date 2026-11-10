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
        return $this->submission->receipt ? 'Payment receipt to verify' : 'Payment to confirm';
    }

    protected function body(): string
    {
        $what = $this->submission->receipt ? 'uploaded a '.Money::format($this->submission->amount).' receipt' : 'says they paid '.Money::format($this->submission->amount).' by bank transfer';

        return "{$this->submission->submitter->name} {$what} for {$this->submission->invoice->number} ({$this->submission->property->identifier}).";
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
