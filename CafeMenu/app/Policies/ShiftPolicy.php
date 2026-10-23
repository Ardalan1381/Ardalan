<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shifts.view_own') || $user->hasPermission('shifts.view_all');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->id === $shift->user_id || $user->hasPermission('shifts.view_all');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('shifts.view_own');
    }

    public function recordCashMovement(User $user, Shift $shift): bool
    {
        return $user->id === $shift->user_id || $user->hasPermission('shifts.manage_all');
    }

    public function close(User $user, Shift $shift): bool
    {
        return $user->id === $shift->user_id || $user->hasPermission('shifts.manage_all');
    }
}
