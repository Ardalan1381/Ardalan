<?php

namespace App\Policies;

use App\Models\TableSection;
use App\Models\User;

class TableSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tables.manage');
    }

    public function view(User $user, TableSection $tableSection): bool
    {
        return $user->hasPermission('tables.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tables.manage');
    }

    public function update(User $user, TableSection $tableSection): bool
    {
        return $user->hasPermission('tables.manage');
    }

    public function delete(User $user, TableSection $tableSection): bool
    {
        return $user->hasPermission('tables.manage');
    }
}
