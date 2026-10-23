<?php

namespace App\Policies;

use App\Models\InventoryCategory;
use App\Models\User;

class InventoryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view') || $user->hasPermission('inventory.manage');
    }

    public function view(User $user, InventoryCategory $category): bool
    {
        return $user->hasPermission('inventory.view') || $user->hasPermission('inventory.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function update(User $user, InventoryCategory $category): bool
    {
        return $user->hasPermission('inventory.manage');
    }

    public function delete(User $user, InventoryCategory $category): bool
    {
        return $user->hasPermission('inventory.manage');
    }
}
