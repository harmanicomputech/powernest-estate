<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'voided_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'subtotal' => 'integer',
            'adjustment_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function billedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'billed_user_id');
    }

    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(ChargeType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InvoiceAdjustment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function balance(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true) && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true) && $this->balance() > 0;
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value]);
    }
}
