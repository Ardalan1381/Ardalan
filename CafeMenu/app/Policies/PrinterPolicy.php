<?php

namespace App\Policies;

use App\Models\Printer;
use App\Models\User;

class PrinterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('printers.manage');
    }

    public function view(User $user, Printer $printer): bool
    {
        return $user->hasPermission('printers.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('printers.manage');
    }

    public function update(User $user, Printer $printer): bool
    {
        return $user->hasPermission('printers.manage');
    }

    public function delete(User $user, Printer $printer): bool
    {
        return $user->hasPermission('printers.manage');
    }
}
