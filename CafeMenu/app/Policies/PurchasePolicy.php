<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchases.view') || $user->hasPermission('purchases.manage');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->hasPermission('purchases.view') || $user->hasPermission('purchases.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchases.manage');
    }

    public function receive(User $user, Purchase $purchase): bool
    {
        return $user->hasPermission('purchases.manage');
    }

    public function updatePaymentStatus(User $user, Purchase $purchase): bool
    {
        return $user->hasPermission('purchases.manage');
    }
}
