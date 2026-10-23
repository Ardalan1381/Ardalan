<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function deactivate(User $user, User $target): bool
    {
        if (! $user->hasPermission('users.manage')) {
            return false;
        }

        return ! $user->is($target);
    }
}
