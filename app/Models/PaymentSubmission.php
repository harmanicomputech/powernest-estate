<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Proof of an offline payment (bank transfer, cash, POS) uploaded by a resident and
 * verified by estate finance before it is applied to an invoice.
 */
class PaymentSubmission extends Model
{
    use BelongsToEstate;

    public const METHODS = ['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'pos' => 'POS', 'cheque' => 'Cheque'];

    protected $guarded = ['id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'amount' => 'integer',
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function receipt(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable')->where('category', 'payment_receipt');
    }
}
