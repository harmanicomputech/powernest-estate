<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meter extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    /**
     * Mirror database defaults for freshly created models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PowerPurchase::class);
    }
}
