<?php

namespace App\Models;

use App\Enums\OccupancyRelationship;
use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Occupancy extends Model
{
    use HasFactory, BelongsToEstate;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'relationship' => OccupancyRelationship::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_primary' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryOccupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'primary_occupancy_id');
    }

    public function householdMembers(): HasMany
    {
        return $this->hasMany(Occupancy::class, 'primary_occupancy_id');
    }
}
