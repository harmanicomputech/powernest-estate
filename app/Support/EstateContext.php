<?php

namespace App\Support;

use App\Enums\EstateRole;
use App\Enums\Permission;
use App\Models\Estate;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The estate the authenticated user is currently operating in, resolved and
 * verified against active memberships by the ResolveEstateContext middleware.
 */
class EstateContext
{
    private ?Estate $estate = null;

    private ?User $user = null;

    public function set(User $user, Estate $estate): void
    {
        $this->user = $user;
        $this->estate = $estate;
    }

    public function clear(): void
    {
        $this->user = null;
        $this->estate = null;
    }

    public function hasEstate(): bool
    {
        return $this->estate !== null;
    }

    public function estate(): Estate
    {
        abort_if($this->estate === null, 404);

        return $this->estate;
    }

    public function estateOrNull(): ?Estate
    {
        return $this->estate;
    }

    /**
     * @return Collection<int, EstateRole>
     */
    public function roles(): Collection
    {
        return $this->user && $this->estate ? $this->user->rolesIn($this->estate) : collect();
    }

    public function can(Permission $permission): bool
    {
        return $this->user && $this->estate && $this->user->hasEstatePermission($this->estate, $permission);
    }

    /**
     * Staff experience (sidebar operations console) vs community experience (resident app).
     */
    public function isStaff(): bool
    {
        return $this->roles()->contains(fn (EstateRole $role) => $role->isStaff());
    }

    public function isCommunity(): bool
    {
        return $this->roles()->contains(fn (EstateRole $role) => $role->isCommunity());
    }
}
