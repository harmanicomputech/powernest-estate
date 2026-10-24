<?php

namespace App\Models;

use App\Enums\PowerPurchaseStatus;
use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An electricity purchase. Status changes only through PowerPurchaseService.
 */
class PowerPurchase extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PowerPurchaseStatus::class,
            'vend_amount' => 'integer',
            'service_charge' => 'integer',
            'total_amount' => 'integer',
            'amount_paid' => 'integer',
            'paid_at' => 'datetime',
            'last_vend_attempt_at' => 'datetime',
            'token_issued_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Issued by estate staff without payment (no gateway involved).
     */
    public function isOfficeVend(): bool
    {
        return $this->gateway === 'office';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function token(): HasOne
    {
        return $this->hasOne(PowerToken::class);
    }

    public function vendAttempts(): HasMany
    {
        return $this->hasMany(VendAttempt::class)->orderBy('attempt');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PowerPurchaseEvent::class)->orderBy('id');
    }
}
