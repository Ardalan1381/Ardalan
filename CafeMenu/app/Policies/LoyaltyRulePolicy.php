<?php

namespace App\Policies;

use App\Models\LoyaltyRule;
use App\Models\User;

class LoyaltyRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('loyalty.view') || $user->hasPermission('loyalty.manage');
    }

    public function view(User $user, LoyaltyRule $loyaltyRule): bool
    {
        return $user->hasPermission('loyalty.view') || $user->hasPermission('loyalty.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('loyalty.manage');
    }

    public function update(User $user, LoyaltyRule $loyaltyRule): bool
    {
        return $user->hasPermission('loyalty.manage');
    }

    public function delete(User $user, LoyaltyRule $loyaltyRule): bool
    {
        return $user->hasPermission('loyalty.manage');
    }
}
