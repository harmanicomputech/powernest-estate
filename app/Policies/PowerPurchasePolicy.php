<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PowerPurchase;
use App\Models\User;

class PowerPurchasePolicy
{
    public function view(User $user, PowerPurchase $purchase): bool
    {
        return $purchase->user_id === $user->id || $user->hasEstatePermission($purchase->estate, Permission::PowerViewAll);
    }

    public function reconcile(User $user, PowerPurchase $purchase): bool
    {
        return $user->hasEstatePermission($purchase->estate, Permission::PowerReconcile);
    }
}
