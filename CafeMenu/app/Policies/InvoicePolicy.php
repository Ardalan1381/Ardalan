<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view') || $user->hasPermission('invoices.manage');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.view') || $user->hasPermission('invoices.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.manage');
    }
}
