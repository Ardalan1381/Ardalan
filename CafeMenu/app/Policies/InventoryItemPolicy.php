<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view') || $user->hasPermission('inventory.manage');
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('inventory.view') || $user->hasPermission('inventory.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('inventory.manage');
    }
}
