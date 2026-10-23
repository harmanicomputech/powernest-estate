<?php

namespace App\Models;

use App\Enums\OccupancyRelationship;
use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipRequest extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'relationship' => OccupancyRelationship::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
