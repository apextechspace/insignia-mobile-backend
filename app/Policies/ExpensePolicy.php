<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpensePolicy
{
    use HandlesAuthorization;

    /**
     * IPL admins see all expenses.
     * MCL (delivery_partner) and guest_relations see only the ones they logged.
     * Staff can only see their own.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER,
            User::ROLE_GUEST_RELATIONS,
            User::ROLE_STAFF,
        ]);
    }

    public function view(User $user, Expense $expense): bool
    {
        if ($user->isIplAdmin()) {
            return true;
        }
        return $expense->logged_by === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER,
            User::ROLE_GUEST_RELATIONS,
        ]);
    }

    public function update(User $user, Expense $expense): bool
    {
        if ($user->isIplAdmin()) {
            return true;
        }
        // MCL/front-desk can only edit their own pending expenses
        return $expense->logged_by === $user->id && $expense->status === 'pending_approval';
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->isIplAdmin();
    }

    /**
     * Only IPL can approve or reject expenses.
     */
    public function approve(User $user): bool
    {
        return $user->isIplAdmin();
    }
}
