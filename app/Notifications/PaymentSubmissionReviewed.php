<?php

namespace App\Notifications;

use App\Enums\SubmissionStatus;
use App\Models\PaymentSubmission;

class PaymentSubmissionReviewed extends AppNotification
{
    protected string $category = 'billing';

    protected bool $essential = true;

    public function __construct(public PaymentSubmission $submission) {}

    protected function title(): string
    {
        return $this->submission->status === SubmissionStatus::Approved ? 'Payment confirmed' : 'Payment receipt not accepted';
    }

    protected function body(): string
    {
        return $this->submission->status === SubmissionStatus::Approved
            ? 'Your offline payment for '.$this->submission->invoice->number.' has been verified and applied.'
            : 'Your receipt for '.$this->submission->invoice->number.' was not accepted: '.$this->submission->review_note.' You can upload a new one.';
    }

    protected function url(): ?string
    {
        return route('bills.show', $this->submission->invoice);
    }

    protected function icon(): string
    {
        return $this->submission->status === SubmissionStatus::Approved ? 'circle-check' : 'circle-x';
    }

    protected function estateId(): ?int
    {
        return $this->submission->estate_id;
    }
}
