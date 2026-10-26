<?php

namespace App\Policies;

use App\Models\AssetHandover;
use App\Models\User;

class AssetHandoverPolicy
{
    public function viewAny(User $user): bool
    {
        // Front desk, MCL, and IPL can view handover protocols
        return !$user->isStaff();
    }

    public function view(User $user, AssetHandover $handover): bool
    {
        return !$user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isIplAdmin() || $user->isDeliveryPartner();
    }

    public function update(User $user, AssetHandover $handover): bool
    {
        // Once locked by IPL, only IPL can edit
        if ($handover->status === 'locked_and_signed_by_ipl') {
            return $user->isIplAdmin();
        }

        return $user->isIplAdmin() || $user->isDeliveryPartner();
    }

    public function delete(User $user, AssetHandover $handover): bool
    {
        return $user->isIplAdmin() && $handover->status !== 'locked_and_signed_by_ipl';
    }
}
