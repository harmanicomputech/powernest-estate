<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id || $user->hasEstatePermission($booking->estate, Permission::FacilitiesManage);
    }

    public function review(User $user, Booking $booking): bool
    {
        return $user->hasEstatePermission($booking->estate, Permission::FacilitiesManage);
    }
}
