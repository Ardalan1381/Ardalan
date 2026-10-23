<?php

namespace App\Actions\Inventory;

use App\Models\StockCount;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Locks a stock count and emits count_correction movements. The correction
 * for each item is computed against that item's LIVE current stock at
 * completion time (not the snapshot taken when the count started) — so
 * the corrected stock always lands exactly on the physically counted
 * quantity, even if other movements happened during the counting window.
 * The stored 'variance' column stays as counted-vs-expected-at-start,
 * purely informational.
 */
class CompleteStockCountAction
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    public function execute(StockCount $count, User $actor): StockCount
    {
        if ($count->status !== 'draft') {
            throw ValidationException::withMessages(['count' => 'This count has already been completed.']);
        }

        return DB::transaction(function () use ($count, $actor) {
            foreach ($count->items()->with('inventoryItem')->get() as $item) {
                if ($item->counted_quantity === null) {
                    continue;
                }

                $delta = Quantity::subtract((string) $item->counted_quantity, (string) $item->inventoryItem->current_stock);

                if (Quantity::compare($delta, '0') === 0) {
                    continue;
                }

                $this->stockMovements->record(
                    $item->inventoryItem,
                    'count_correction',
                    $delta,
                    reference: $count,
                    actor: $actor,
                    allowNegative: true,
                );
            }

            $count->update(['status' => 'completed', 'counted_at' => now()]);

            return $count->fresh('items');
        });
    }
}
