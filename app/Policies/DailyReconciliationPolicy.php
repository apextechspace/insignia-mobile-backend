<?php

namespace App\Policies;

use App\Models\DailyReconciliation;
use App\Models\User;

class DailyReconciliationPolicy
{
    /**
     * Staff have zero access to daily cash/POS reconciliations.
     */
    public function viewAny(User $user): bool
    {
        return !$user->isStaff();
    }

    public function view(User $user, DailyReconciliation $reconciliation): bool
    {
        return !$user->isStaff();
    }

    public function create(User $user): bool
    {
        // Front desk, MCL, and IPL can submit closing reconciliations
        return !$user->isStaff();
    }

    public function update(User $user, DailyReconciliation $reconciliation): bool
    {
        // Unapproved reconciliations can be edited by MCL or IPL
        if ($reconciliation->status === 'approved_by_ipl') {
            return false;
        }

        return $user->isIplAdmin() || $user->isDeliveryPartner();
    }

    public function delete(User $user, DailyReconciliation $reconciliation): bool
    {
        return $user->isIplAdmin() && $reconciliation->status !== 'approved_by_ipl';
    }
}
