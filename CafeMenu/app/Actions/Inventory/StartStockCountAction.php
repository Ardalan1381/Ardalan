<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartStockCountAction
{
    /**
     * @param  array<int, int>  $inventoryItemIds
     */
    public function execute(array $inventoryItemIds, User $actor): StockCount
    {
        return DB::transaction(function () use ($inventoryItemIds, $actor) {
            $count = StockCount::create([
                'status' => 'draft',
                'created_by_user_id' => $actor->id,
            ]);

            foreach (InventoryItem::query()->whereIn('id', $inventoryItemIds)->get() as $item) {
                $count->items()->create([
                    'inventory_item_id' => $item->id,
                    'expected_quantity' => $item->current_stock,
                ]);
            }

            return $count->fresh('items');
        });
    }
}
