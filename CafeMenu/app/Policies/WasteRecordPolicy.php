<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WasteRecord;

class WasteRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function view(User $user, WasteRecord $waste): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }
}
