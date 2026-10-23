<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    /**
     * Mirror database defaults for freshly created models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, 'requires_approval' => true, 'fee' => 0, 'opens_at' => '07:00', 'closes_at' => '21:00', 'max_duration_minutes' => 180, 'advance_booking_days' => 30];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
