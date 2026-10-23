<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Illuminate\Support\Facades\DB;

class RecordStockAdjustmentAction
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{inventory_item_id: int, quantity_delta: string, unit_cost: ?int}>  $lines
     */
    public function execute(array $data, array $lines, User $actor): StockAdjustment
    {
        return DB::transaction(function () use ($data, $lines, $actor) {
            $adjustment = StockAdjustment::create([
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $item = InventoryItem::findOrFail($line['inventory_item_id']);
                $delta = (string) $line['quantity_delta'];

                $adjustment->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity_delta' => $delta,
                    'unit_cost' => $line['unit_cost'] ?? null,
                ]);

                $this->stockMovements->record(
                    $item,
                    str_starts_with($delta, '-') ? 'adjustment_decrease' : 'adjustment_increase',
                    $delta,
                    unitCost: $line['unit_cost'] ?? null,
                    reference: $adjustment,
                    notes: $data['notes'] ?? null,
                    actor: $actor,
                );
            }

            return $adjustment->fresh('items');
        });
    }
}
