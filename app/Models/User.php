<?php

namespace App\Models;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Privileged columns (status, is_platform_admin, current_estate_id) are never mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'password'];

    /**
     * Mirror database defaults so freshly created users behave correctly in the same request.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active', 'is_platform_admin' => false, 'current_estate_id' => null];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * Per-request cache of roles keyed by estate id.
     *
     * @var array<int, Collection<int, EstateRole>>
     */
    private array $rolesCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(EstateMembership::class);
    }

    public function estates(): BelongsToMany
    {
        return $this->belongsToMany(Estate::class, 'estate_memberships')
            ->wherePivot('status', MembershipStatus::Active->value)
            ->where('estates.status', 'active')
            ->distinct();
    }

    public function currentEstate(): BelongsTo
    {
        return $this->belongsTo(Estate::class, 'current_estate_id');
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(Occupancy::class);
    }

    public function favouriteMeters(): BelongsToMany
    {
        return $this->belongsToMany(Meter::class, 'meter_favourites')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return Collection<int, EstateRole>
     */
    public function rolesIn(Estate|int $estate): Collection
    {
        $estateId = $estate instanceof Estate ? $estate->getKey() : $estate;

        return $this->rolesCache[$estateId] ??= $this->memberships()
            ->where('estate_id', $estateId)
            ->where('status', MembershipStatus::Active->value)
            ->pluck('role')
            ->map(fn (EstateRole|string $role) => $role instanceof EstateRole ? $role : EstateRole::tryFrom($role))
            ->filter()
            ->values();
    }

    public function hasRoleIn(Estate|int $estate, EstateRole ...$roles): bool
    {
        return $this->rolesIn($estate)->contains(fn (EstateRole $role) => in_array($role, $roles, true));
    }

    public function hasEstatePermission(Estate|int $estate, Permission $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($estate instanceof Estate && ! $estate->isOperational()) {
            return false;
        }

        $estateModel = $estate instanceof Estate ? $estate : Estate::find($estate);

        return $estateModel !== null
            && $this->rolesIn($estateModel)->contains(fn (EstateRole $role) => in_array($permission, $estateModel->permissionsFor($role), true));
    }

    public function isMemberOf(Estate|int $estate): bool
    {
        return $this->rolesIn($estate)->isNotEmpty();
    }

    public function flushRoleCache(): void
    {
        $this->rolesCache = [];
    }

    /**
     * Properties the user may act for in an estate (owner, landlord, tenant, household member).
     */
    public function authorizedProperties(Estate|int $estate): Builder
    {
        $estateId = $estate instanceof Estate ? $estate->getKey() : $estate;

        return Property::query()
            ->where('estate_id', $estateId)
            ->whereHas('occupancies', fn (Builder $q) => $q->where('user_id', $this->id)->where('status', 'active'));
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
    }

    public function firstName(): string
    {
        return Str::before($this->name, ' ') ?: $this->name;
    }

    public function wantsNotification(string $category, string $channel): bool
    {
        return (bool) data_get($this->notification_preferences, "{$category}.{$channel}", true);
    }
}
