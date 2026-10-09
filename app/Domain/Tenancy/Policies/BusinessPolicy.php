<?php

namespace App\Domain\Tenancy\Policies;

use App\Domain\Tenancy\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $user->roleIn($business) !== null;
    }

    public function update(User $user, Business $business): bool
    {
        return $user->isOwnerOf($business);
    }

    public function manageTeam(User $user, Business $business): bool
    {
        return $user->isOwnerOf($business);
    }

    public function manageBilling(User $user, Business $business): bool
    {
        return $user->isOwnerOf($business);
    }
}
