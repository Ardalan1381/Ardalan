<?php

namespace App\Policies;

use App\Models\Table;
use App\Models\User;

class TablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tables.manage');
    }

    public function view(User $user, Table $table): bool
    {
        return $user->hasPermission('tables.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tables.manage');
    }

    public function update(User $user, Table $table): bool
    {
        return $user->hasPermission('tables.manage');
    }

    public function delete(User $user, Table $table): bool
    {
        return $user->hasPermission('tables.manage');
    }
}
