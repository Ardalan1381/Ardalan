<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('categories.manage');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasPermission('categories.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('categories.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermission('categories.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermission('categories.manage');
    }
}
