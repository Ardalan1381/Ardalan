<?php

namespace App\Actions\Inventory;

use App\Models\Purchase;

class MarkPurchasePaidAction
{
    public function execute(Purchase $purchase, string $status): Purchase
    {
        $purchase->update(['payment_status' => $status]);

        return $purchase;
    }
}
