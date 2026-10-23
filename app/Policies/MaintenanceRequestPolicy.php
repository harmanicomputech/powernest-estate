<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function view(User $user, MaintenanceRequest $request): bool
    {
        return $request->requested_by === $user->id
            || $request->assigned_to === $user->id
            || $user->hasEstatePermission($request->estate, Permission::MaintenanceManage)
            || ($request->property_id && $user->hasEstatePermission($request->estate, Permission::MaintenanceRequest) && $user->authorizedProperties($request->estate_id)->whereKey($request->property_id)->exists());
    }

    public function manage(User $user, MaintenanceRequest $request): bool
    {
        return $user->hasEstatePermission($request->estate, Permission::MaintenanceManage);
    }

    public function work(User $user, MaintenanceRequest $request): bool
    {
        return $this->manage($user, $request)
            || ($request->assigned_to === $user->id && $user->hasEstatePermission($request->estate, Permission::MaintenanceWork));
    }

    public function viewInternal(User $user, MaintenanceRequest $request): bool
    {
        return $this->work($user, $request);
    }
}
