<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->payer_id === $user->id
            || $user->hasEstatePermission($payment->estate, Permission::BillingManage)
            || ($payment->property_id && $user->hasEstatePermission($payment->estate, Permission::BillingViewOwn) && $user->authorizedProperties($payment->estate_id)->whereKey($payment->property_id)->exists());
    }
}
