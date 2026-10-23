<?php

namespace App\Actions\Inventory;

use App\Models\StockCount;
use App\Support\Quantity;
use Illuminate\Validation\ValidationException;

class SubmitStockCountAction
{
    /**
     * @param  array<int, array{stock_count_item_id: int, counted_quantity: string}>  $counted
     */
    public function execute(StockCount $count, array $counted): StockCount
    {
        if ($count->status !== 'draft') {
            throw ValidationException::withMessages(['count' => 'This count has already been completed.']);
        }

        foreach ($counted as $entry) {
            $item = $count->items()->findOrFail($entry['stock_count_item_id']);
            $countedQuantity = (string) $entry['counted_quantity'];

            $item->update([
                'counted_quantity' => $countedQuantity,
                'variance' => Quantity::subtract($countedQuantity, (string) $item->expected_quantity),
            ]);
        }

        return $count->fresh('items');
    }
}
