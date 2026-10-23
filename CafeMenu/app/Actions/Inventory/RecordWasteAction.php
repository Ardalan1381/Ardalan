<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WasteRecord;
use App\Services\Inventory\StockMovementService;
use Illuminate\Support\Facades\DB;

class RecordWasteAction
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(InventoryItem $item, array $data, User $actor): WasteRecord
    {
        return DB::transaction(function () use ($item, $data, $actor) {
            $unitCost = $data['unit_cost'] ?? $item->average_cost;

            $waste = WasteRecord::create([
                'inventory_item_id' => $item->id,
                'quantity' => $data['quantity'],
                'unit_cost' => $unitCost,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'wasted_at' => $data['wasted_at'] ?? now(),
                'created_by_user_id' => $actor->id,
            ]);

            $this->stockMovements->record(
                $item,
                'waste',
                '-'.$data['quantity'],
                unitCost: $unitCost,
                reference: $waste,
                notes: $data['notes'] ?? null,
                actor: $actor,
            );

            return $waste;
        });
    }
}
