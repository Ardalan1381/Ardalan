<?php

namespace App\Policies;

use App\Models\LoyaltyReward;
use App\Models\User;

class LoyaltyRewardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('loyalty.view') || $user->hasPermission('loyalty.manage');
    }

    public function view(User $user, LoyaltyReward $loyaltyReward): bool
    {
        return $user->hasPermission('loyalty.view') || $user->hasPermission('loyalty.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('loyalty.manage');
    }

    public function update(User $user, LoyaltyReward $loyaltyReward): bool
    {
        return $user->hasPermission('loyalty.manage');
    }

    public function delete(User $user, LoyaltyReward $loyaltyReward): bool
    {
        return $user->hasPermission('loyalty.manage');
    }
}
