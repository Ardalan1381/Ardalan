<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission('orders.view');
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->hasPermission('orders.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('orders.create');
    }

    public function addItems(User $user, Order $order): bool
    {
        return $user->hasPermission('orders.create');
    }
}
