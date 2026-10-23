<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\PurchaseReturn;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Illuminate\Support\Facades\DB;

class RecordPurchaseReturnAction
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{inventory_item_id: int, quantity: string, unit_cost: int}>  $lines
     */
    public function execute(array $data, array $lines, User $actor): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $lines, $actor) {
            $totalAmount = 0;

            foreach ($lines as $line) {
                $totalAmount += (int) round((float) bcmul((string) $line['quantity'], (string) $line['unit_cost'], 6));
            }

            $return = PurchaseReturn::create([
                'purchase_id' => $data['purchase_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'reason' => $data['reason'] ?? null,
                'total_amount' => $totalAmount,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $item = InventoryItem::findOrFail($line['inventory_item_id']);

                $return->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ]);

                $this->stockMovements->record(
                    $item,
                    'return_to_supplier',
                    '-'.$line['quantity'],
                    unitCost: $line['unit_cost'],
                    reference: $return,
                    actor: $actor,
                );
            }

            return $return->fresh('items');
        });
    }
}
