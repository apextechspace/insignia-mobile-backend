<?php

namespace App\Policies;

use App\Models\PettyCashTransaction;
use App\Models\User;

class PettyCashTransactionPolicy
{
    /**
     * Housekeeping/Floor staff have zero access to petty cash records.
     */
    public function viewAny(User $user): bool
    {
        return !$user->isStaff();
    }

    public function view(User $user, PettyCashTransaction $transaction): bool
    {
        return !$user->isStaff();
    }

    public function create(User $user): bool
    {
        // Front Desk, MCL, and IPL can record transactions
        return !$user->isStaff();
    }

    public function update(User $user, PettyCashTransaction $transaction): bool
    {
        // Only IPL Master Admin can modify historical petty cash entries
        return $user->isIplAdmin();
    }

    public function delete(User $user, PettyCashTransaction $transaction): bool
    {
        // Only IPL Master Admin can delete (for audit integrity)
        return $user->isIplAdmin();
    }
}
