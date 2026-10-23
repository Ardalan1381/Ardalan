<?php

namespace App\Policies;

use App\Models\BillSplit;
use App\Models\User;

class BillSplitPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('orders.split');
    }

    public function view(User $user, BillSplit $billSplit): bool
    {
        return $user->hasPermission('orders.split');
    }

    public function recordPayment(User $user, BillSplit $billSplit): bool
    {
        return $user->hasPermission('orders.split');
    }
}
