<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }
}
