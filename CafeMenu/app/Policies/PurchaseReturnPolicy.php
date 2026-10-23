<?php

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;

/**
 * Purchase returns move stock (they emit a return_to_supplier movement),
 * so they are gated on inventory.adjust — the same trust level as manual
 * stock adjustments and waste — rather than purchases.manage, which only
 * covers creating/receiving purchases.
 */
class PurchaseReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function view(User $user, PurchaseReturn $return): bool
    {
        return $user->hasPermission('inventory.adjust');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.adjust');
    }
}
