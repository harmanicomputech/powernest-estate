<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargeType extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    /**
     * Mirror database defaults for freshly created models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'property_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
