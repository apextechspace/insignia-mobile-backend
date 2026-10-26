<?php

namespace App\Policies;

use App\Models\AccommodationLedger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccommodationLedgerPolicy
{
    use HandlesAuthorization;

    /**
     * Both IPL and MCL (delivery_partner) can view accommodation revenue.
     * Guest relations and staff are blocked.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER,
        ]);
    }

    public function view(User $user, AccommodationLedger $ledger): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER,
        ]);
    }

    /**
     * Only IPL can manually create ledger entries.
     * Automated entries are created by BookingObserver.
     */
    public function create(User $user): bool
    {
        return $user->isIplAdmin();
    }

    public function update(User $user, AccommodationLedger $ledger): bool
    {
        return $user->isIplAdmin();
    }

    public function delete(User $user, AccommodationLedger $ledger): bool
    {
        return $user->isIplAdmin();
    }
}
