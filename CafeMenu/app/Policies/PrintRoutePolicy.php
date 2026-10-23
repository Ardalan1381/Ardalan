<?php

namespace App\Policies;

use App\Models\PrintRoute;
use App\Models\User;

class PrintRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('printers.manage');
    }

    public function view(User $user, PrintRoute $printRoute): bool
    {
        return $user->hasPermission('printers.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('printers.manage');
    }

    public function update(User $user, PrintRoute $printRoute): bool
    {
        return $user->hasPermission('printers.manage');
    }

    public function delete(User $user, PrintRoute $printRoute): bool
    {
        return $user->hasPermission('printers.manage');
    }
}
