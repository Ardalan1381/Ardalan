<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single choke point every inventory mutation must go through — no
 * caller ever writes InventoryItem::current_stock directly. Every change is
 * an append-only movement row; current_stock is only ever a cache of the
 * running sum, kept in sync here.
 */
class StockMovementService
{
    public function record(
        InventoryItem $item,
        string $type,
        string $quantityDelta,
        ?int $unitCost = null,
        ?Model $reference = null,
        ?string $notes = null,
        ?User $actor = null,
        bool $allowNegative = false,
    ): StockMovement {
        return DB::transaction(function () use ($item, $type, $quantityDelta, $unitCost, $reference, $notes, $actor, $allowNegative) {
            /** @var InventoryItem $locked */
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            $previousStock = (string) $locked->current_stock;
            $resultingStock = Quantity::add($previousStock, $quantityDelta);

            if (Quantity::isNegative($resultingStock) && ! $allowNegative) {
                throw ValidationException::withMessages([
                    'quantity' => "This would take \"{$locked->name}\" below zero stock.",
                ]);
            }

            $movement = StockMovement::create([
                'inventory_item_id' => $locked->id,
                'type' => $type,
                'quantity' => $quantityDelta,
                'resulting_stock' => $resultingStock,
                'unit_cost' => $unitCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by_user_id' => $actor?->id,
            ]);

            $locked->current_stock = $resultingStock;

            if ($type === 'purchase_receipt' && $unitCost !== null && ! Quantity::isNegative($quantityDelta)) {
                $locked->average_cost = $this->weightedAverageCost($locked, $previousStock, $quantityDelta, $unitCost);
            }

            if ($locked->min_stock_level !== null && Quantity::compare($resultingStock, (string) $locked->min_stock_level) > 0) {
                $locked->low_stock_alerted_at = null;
            }

            $locked->save();

            return $movement;
        });
    }

    /**
     * Weighted moving average: blends the existing on-hand value with the
     * newly received value, weighted by quantity.
     */
    private function weightedAverageCost(InventoryItem $item, string $previousStock, string $addedQty, int $addedUnitCost): int
    {
        $previousAverage = $item->average_cost;

        if ($previousAverage === null || Quantity::compare($previousStock, '0') <= 0) {
            return $addedUnitCost;
        }

        $previousValue = bcmul($previousStock, (string) $previousAverage, 6);
        $addedValue = bcmul($addedQty, (string) $addedUnitCost, 6);
        $totalQty = Quantity::add($previousStock, $addedQty);

        if (Quantity::compare($totalQty, '0') <= 0) {
            return $addedUnitCost;
        }

        $newAverage = bcdiv(bcadd($previousValue, $addedValue, 6), $totalQty, 6);

        return (int) round((float) $newAverage);
    }
}
