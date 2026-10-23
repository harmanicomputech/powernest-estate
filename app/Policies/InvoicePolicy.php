<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasEstatePermission($invoice->estate, Permission::BillingManage) || $this->pay($user, $invoice);
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $user->hasEstatePermission($invoice->estate, Permission::BillingViewOwn)
            && $user->authorizedProperties($invoice->estate_id)->whereKey($invoice->property_id)->exists();
    }

    public function manage(User $user, Invoice $invoice): bool
    {
        return $user->hasEstatePermission($invoice->estate, Permission::BillingManage);
    }
}
