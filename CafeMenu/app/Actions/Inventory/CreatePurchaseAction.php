<?php

namespace App\Actions\Inventory;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePurchaseAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $actor): Purchase
    {
        return DB::transaction(function () use ($data, $actor) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'purchase_date' => $data['purchase_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            $subtotal = 0;

            foreach ($data['items'] as $item) {
                $lineTotal = $this->lineTotal((string) $item['quantity'], (int) $item['unit_cost']);

                $purchase->items()->create([
                    'inventory_item_id' => $item['inventory_item_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $purchase->update([
                'subtotal' => $subtotal,
                'total_cost' => $subtotal + $purchase->tax_total,
            ]);

            return $purchase->fresh('items');
        });
    }

    private function lineTotal(string $quantity, int $unitCost): int
    {
        return (int) round((float) bcmul($quantity, (string) $unitCost, 6));
    }
}
