<?php

namespace App\Policies;

use App\Models\InvoiceTemplate;
use App\Models\User;

class InvoiceTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.manage');
    }

    public function view(User $user, InvoiceTemplate $invoiceTemplate): bool
    {
        return $user->hasPermission('invoices.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.manage');
    }

    public function update(User $user, InvoiceTemplate $invoiceTemplate): bool
    {
        return $user->hasPermission('invoices.manage');
    }

    public function delete(User $user, InvoiceTemplate $invoiceTemplate): bool
    {
        return $user->hasPermission('invoices.manage');
    }
}
