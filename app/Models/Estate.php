<?php

namespace App\Models;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Enums\Permission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estate extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Mirror database defaults for freshly created models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN', 'country' => 'NG'];

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

    /**
     * Permissions a role has in this estate: the estate's customised set when one was saved,
     * otherwise the role's built-in defaults. Owners always keep every permission.
     *
     * @return list<Permission>
     */
    public function permissionsFor(EstateRole $role): array
    {
        $custom = data_get($this->settings, 'role_permissions.'.$role->value);

        if ($role === EstateRole::EstateOwner || ! is_array($custom)) {
            return $role->permissions();
        }

        return array_values(array_filter(array_map(fn (string $value) => Permission::tryFrom($value), $custom)));
    }

    public function timezone(): string
    {
        return $this->timezone ?: config('powernest.display_timezone');
    }
}
