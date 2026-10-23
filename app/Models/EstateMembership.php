<?php

namespace App\Models;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstateMembership extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'role' => EstateRole::class,
            'status' => MembershipStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
