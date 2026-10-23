<?php

namespace App\Policies;

use App\Models\Discount;
use App\Models\User;

class DiscountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('discounts.view') || $user->hasPermission('discounts.manage');
    }

    public function view(User $user, Discount $discount): bool
    {
        return $user->hasPermission('discounts.view') || $user->hasPermission('discounts.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('discounts.manage');
    }

    public function update(User $user, Discount $discount): bool
    {
        return $user->hasPermission('discounts.manage');
    }

    public function delete(User $user, Discount $discount): bool
    {
        return $user->hasPermission('discounts.manage');
    }
}
