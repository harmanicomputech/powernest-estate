<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\VisitorPass;

class VisitorPassPolicy
{
    public function view(User $user, VisitorPass $pass): bool
    {
        return $pass->host_user_id === $user->id || $user->hasEstatePermission($pass->estate, Permission::VisitorsViewAll);
    }

    public function revoke(User $user, VisitorPass $pass): bool
    {
        return $this->view($user, $pass);
    }
}
