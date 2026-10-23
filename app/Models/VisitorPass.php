<?php

namespace App\Models;

use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class VisitorPass extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['token_lookup', 'token_encrypted', 'code_lookup', 'code_encrypted'];

    protected function casts(): array
    {
        return [
            'pass_type' => PassType::class,
            'status' => PassStatus::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'revoked_at' => 'datetime',
            'recurrence' => 'array',
            'token_encrypted' => 'encrypted',
            'code_encrypted' => 'encrypted',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(PassScan::class);
    }

    public function token(): string
    {
        return $this->token_encrypted;
    }

    public function code(): string
    {
        return $this->code_encrypted;
    }

    public function formattedCode(): string
    {
        return trim(chunk_split($this->code(), 3, ' '));
    }

    /**
     * Status as it should be presented now (an active pass past its window reads as expired).
     */
    public function effectiveStatus(?Carbon $now = null): PassStatus
    {
        $now ??= now();

        if ($this->status === PassStatus::Active && $this->valid_until->lt($now)) {
            return PassStatus::Expired;
        }

        return $this->status;
    }

    public function remainingEntries(): ?int
    {
        return $this->max_entries === null ? null : max(0, $this->max_entries - $this->entries_count);
    }
}
