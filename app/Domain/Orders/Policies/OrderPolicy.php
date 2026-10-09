<?php

namespace App\Domain\Orders\Policies;

use App\Domain\Orders\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->canInBranch($order->branch, 'orders');
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->canInBranch($order->branch, 'orders');
    }
}
