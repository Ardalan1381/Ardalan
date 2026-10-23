<?php

namespace App\Policies;

use App\Models\StockCount;
use App\Models\User;

class StockCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function view(User $user, StockCount $count): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function submit(User $user, StockCount $count): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function complete(User $user, StockCount $count): bool
    {
        return $user->hasPermission('inventory.adjust');
    }
}
