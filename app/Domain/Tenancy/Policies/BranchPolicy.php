<?php

namespace App\Domain\Tenancy\Policies;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Role;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user, Business $business): bool
    {
        return $user->roleIn($business) !== null;
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->canAccessBranch($branch);
    }

    /** Creating, activating and deleting branches is an owner-level decision. */
    public function create(User $user, Business $business): bool
    {
        return $user->isOwnerOf($business);
    }

    public function update(User $user, Branch $branch): bool
    {
        $role = $user->roleIn($branch->business);

        return in_array($role, [Role::Owner, Role::Manager], true) && $user->canAccessBranch($branch);
    }

    public function toggleActive(User $user, Branch $branch): bool
    {
        return $user->isOwnerOf($branch->business);
    }

    /** Prices and visibility: owners, and managers of the branch. */
    public function managePricing(User $user, Branch $branch): bool
    {
        return in_array($user->roleIn($branch->business), [Role::Owner, Role::Manager], true) && $user->canAccessBranch($branch);
    }

    /** Availability toggles can be delegated to staff with the menu_availability permission. */
    public function manageAvailability(User $user, Branch $branch): bool
    {
        return $user->canInBranch($branch, 'menu_availability');
    }

    public function viewOrders(User $user, Branch $branch): bool
    {
        return $user->canInBranch($branch, 'orders');
    }
}
