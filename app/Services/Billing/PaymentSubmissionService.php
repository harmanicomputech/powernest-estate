<?php

namespace App\Services\Billing;

use App\Enums\Permission;
use App\Enums\SubmissionStatus;
use App\Models\Invoice;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Notifications\PaymentSubmissionReceived;
use App\Notifications\PaymentSubmissionReviewed;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentStore;
use App\Services\Estate\MembershipService;
use App\Support\Money;
use App\Support\Reference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Offline payments: a resident uploads proof, finance verifies it against the bank statement and
 * approves (which records a normal estate payment against the invoice) or rejects with a reason.
 * Nothing is credited until a reviewer approves.
 */
class PaymentSubmissionService
{
    public function __construct(
        private BillingService $billing,
        private DocumentStore $documents,
        private MembershipService $members,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{amount: int, method: string, payer_reference?: ?string, paid_on: string, note?: ?string}  $data
     */
    public function submit(User $user, Invoice $invoice, array $data, UploadedFile $receipt): PaymentSubmission
    {
        abort_unless(Gate::forUser($user)->allows('pay', $invoice), 403);

        if (! $invoice->isPayable()) {
            throw ValidationException::withMessages(['amount' => 'This invoice has nothing left to pay.']);
        }

        if ($data['amount'] < 1 || $data['amount'] > $invoice->balance()) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount up to the outstanding balance of '.Money::format($invoice->balance()).'.']);
        }

        if ($invoice->submissions()->where('status', SubmissionStatus::Pending->value)->exists()) {
            throw ValidationException::withMessages(['receipt' => 'A receipt for this invoice is already awaiting review.']);
        }

        $submission = DB::transaction(function () use ($user, $invoice, $data, $receipt) {
            $submission = PaymentSubmission::create([
                'estate_id' => $invoice->estate_id,
                'reference' => $this->uniqueReference(),
                'invoice_id' => $invoice->id,
                'property_id' => $invoice->property_id,
                'submitted_by' => $user->id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'payer_reference' => $data['payer_reference'] ?? null,
                'paid_on' => $data['paid_on'],
                'note' => $data['note'] ?? null,
            ]);

            $this->documents->store($submission, $receipt, $user, 'payment_receipt');
            $this->audit->record('payment_submission.created', $submission, ['invoice' => $invoice->number, 'amount' => $data['amount']], $invoice->estate_id, $user);

            return $submission;
        });

        Notification::send($this->members->usersWithPermission($invoice->estate, Permission::BillingManage), new PaymentSubmissionReceived($submission));

        return $submission;
    }

    /**
     * Approving records the payment (receipt, ledger, invoice status) through BillingService.
     * The reviewer may approve a different amount if the bank shows a different figure.
     */
    public function approve(PaymentSubmission $submission, User $reviewer, ?int $amount = null, ?string $note = null): PaymentSubmission
    {
        $this->authorizeReviewer($submission, $reviewer);

        $approved = DB::transaction(function () use ($submission, $reviewer, $amount, $note) {
            $locked = $this->lockPending($submission);
            $amount ??= $locked->amount;

            $payment = $this->billing->recordManualPayment(
                $locked->invoice()->lockForUpdate()->firstOrFail(),
                $amount,
                $locked->method,
                $locked->reference,
                Carbon::parse($locked->paid_on),
                $reviewer,
                trim('Offline payment verified from uploaded receipt.'.($locked->payer_reference ? " Payer ref: {$locked->payer_reference}." : '').($note ? " {$note}" : '')),
            );

            $locked->update(['status' => SubmissionStatus::Approved, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note, 'payment_id' => $payment->id]);
            $this->audit->record('payment_submission.approved', $locked, ['amount' => $amount, 'payment' => $payment->reference], $locked->estate_id, $reviewer);

            return $locked;
        });

        $approved->submitter->notify(new PaymentSubmissionReviewed($approved));

        return $approved;
    }

    public function reject(PaymentSubmission $submission, User $reviewer, string $reason): PaymentSubmission
    {
        $this->authorizeReviewer($submission, $reviewer);

        $rejected = DB::transaction(function () use ($submission, $reviewer, $reason) {
            $locked = $this->lockPending($submission);
            $locked->update(['status' => SubmissionStatus::Rejected, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $reason]);
            $this->audit->record('payment_submission.rejected', $locked, ['reason' => $reason], $locked->estate_id, $reviewer);

            return $locked;
        });

        $rejected->submitter->notify(new PaymentSubmissionReviewed($rejected));

        return $rejected;
    }

    private function lockPending(PaymentSubmission $submission): PaymentSubmission
    {
        $locked = PaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== SubmissionStatus::Pending) {
            throw ValidationException::withMessages(['submission' => 'This receipt has already been reviewed.']);
        }

        return $locked;
    }

    private function authorizeReviewer(PaymentSubmission $submission, User $reviewer): void
    {
        abort_unless($reviewer->hasEstatePermission($submission->estate, Permission::BillingManage), 403);

        if ($submission->submitted_by === $reviewer->id) {
            throw ValidationException::withMessages(['submission' => 'Another staff member must review a receipt you submitted.']);
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = Reference::make('PS', 8);
        } while (PaymentSubmission::where('reference', $reference)->exists());

        return $reference;
    }
}
