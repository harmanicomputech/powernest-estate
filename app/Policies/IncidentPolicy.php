<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Incident;
use App\Models\User;

/**
 * Incident records are restricted: reporters see their own, assigned staff see theirs,
 * and only incident managers see everything.
 */
class IncidentPolicy
{
    public function view(User $user, Incident $incident): bool
    {
        return $incident->reported_by === $user->id
            || $incident->assigned_to === $user->id
            || $user->hasEstatePermission($incident->estate, Permission::IncidentsManage);
    }

    public function manage(User $user, Incident $incident): bool
    {
        return $user->hasEstatePermission($incident->estate, Permission::IncidentsManage);
    }

    public function addNote(User $user, Incident $incident): bool
    {
        return $this->manage($user, $incident) || $incident->assigned_to === $user->id;
    }
}
