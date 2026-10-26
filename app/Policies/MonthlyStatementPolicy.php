<?php

namespace App\Policies;

use App\Models\MonthlyStatement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MonthlyStatementPolicy
{
    use HandlesAuthorization;

    /**
     * IPL sees all statements.
     * MCL (delivery_partner) can see and create statements they submitted.
     * Guest relations and staff are blocked entirely.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER,
        ]);
    }

    public function view(User $user, MonthlyStatement $statement): bool
    {
        if ($user->isIplAdmin()) {
            return true;
        }
        // MCL can view statements they submitted
        return $user->isDeliveryPartner() && $statement->submitted_by === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER,
        ]);
    }

    public function update(User $user, MonthlyStatement $statement): bool
    {
        if ($user->isIplAdmin()) {
            return true;
        }
        // MCL can only edit draft statements they own
        return $user->isDeliveryPartner()
            && $statement->submitted_by === $user->id
            && $statement->status === 'draft';
    }

    public function delete(User $user, MonthlyStatement $statement): bool
    {
        return $user->isIplAdmin();
    }

    /**
     * Only IPL can approve/query a monthly statement.
     */
    public function approve(User $user): bool
    {
        return $user->isIplAdmin();
    }
}
