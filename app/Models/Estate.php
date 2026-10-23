<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estate extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_demo' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(EstateMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', MembershipStatus::Active->value);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function gates(): HasMany
    {
        return $this->hasMany(Gate::class);
    }

    public function isOperational(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Estate setting with fallback to the platform default in config/powernest.php.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, data_get(config('powernest.estate_defaults'), $key, $default));
    }

    public function putSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    public function timezone(): string
    {
        return $this->timezone ?: config('powernest.display_timezone');
    }
}
