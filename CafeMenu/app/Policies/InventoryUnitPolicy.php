<?php

namespace App\Policies;

use App\Models\InventoryUnit;
use App\Models\User;

class InventoryUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view') || $user->hasPermission('inventory.manage');
    }

    public function view(User $user, InventoryUnit $unit): bool
    {
        return $user->hasPermission('inventory.view') || $user->hasPermission('inventory.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function update(User $user, InventoryUnit $unit): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function delete(User $user, InventoryUnit $unit): bool
    {
        return $user->hasPermission('inventory.manage');
    }
}
