<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deducts (or gives back) inventory when an order is confirmed/cancelled.
 *
 * Consumption is resolved from the CURRENT recipe of each product/option
 * at the moment the item is consumed, and recorded per order item as
 * 'sale_consumption' stock movements carrying the item's average cost at
 * that moment. Those movements are the durable history: reversal never
 * re-reads a recipe (which may have been edited since) — it gives back
 * exactly the net quantity the ledger says each order item consumed.
 *
 * Consumption is tracked per order item (order_items.inventory_consumed_at),
 * so items added to an already-confirmed order are consumed on their own,
 * once, without touching the items consumed before.
 */
class RecipeConsumptionService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Consumes every not-yet-consumed item of the order. Idempotent: an
     * item that was already consumed is skipped.
     */
    public function consume(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $items = $order->items()
                ->whereNull('inventory_consumed_at')
                ->with(['product.recipeItems.inventoryItem.baseUnit', 'product.recipeItems.unit', 'options.productOption.recipeItems.inventoryItem.baseUnit', 'options.productOption.recipeItems.unit'])
                ->get();

            foreach ($items as $item) {
                if (! $item->product) {
                    $this->auditLogger->log('inventory.consumption_skipped', $order, new: [
                        'order_item_id' => $item->id,
                        'reason' => 'product no longer exists',
                    ]);
                } else {
                    $this->consumeRecipeLines($item->product->recipeItems, $item);

                    foreach ($item->options as $orderItemOption) {
                        if ($orderItemOption->productOption) {
                            $this->consumeRecipeLines($orderItemOption->productOption->recipeItems, $item);
                        }
                    }
                }

                $item->forceFill(['inventory_consumed_at' => now()])->save();
            }

            if (! $order->inventory_consumed_at) {
                $order->update(['inventory_consumed_at' => now()]);
            }
        });
    }

    /**
     * Gives back, per order item, exactly the net quantity its stock
     * movements recorded (consumption minus any earlier reversal).
     */
    public function reverse(Order $order): void
    {
        if (! $order->inventory_consumed_at) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items()->get() as $item) {
                $this->reverseItem($item);
            }

            $order->update(['inventory_consumed_at' => null]);
        });
    }

    private function reverseItem(OrderItem $item): void
    {
        $netByInventoryItem = StockMovement::query()
            ->where('reference_type', $item->getMorphClass())
            ->where('reference_id', $item->getKey())
            ->whereIn('type', ['sale_consumption', 'sale_reversal'])
            ->get(['inventory_item_id', 'quantity'])
            ->groupBy('inventory_item_id')
            ->map(fn ($movements) => $movements->reduce(fn (string $sum, StockMovement $m) => Quantity::add($sum, (string) $m->quantity), '0'));

        foreach ($netByInventoryItem as $inventoryItemId => $net) {
            // $net is negative for whatever is still consumed.
            if (! Quantity::isNegative($net)) {
                continue;
            }

            $this->stockMovements->record(
                InventoryItem::findOrFail($inventoryItemId),
                'sale_reversal',
                Quantity::multiply($net, '-1'),
                reference: $item,
                allowNegative: true,
            );
        }

        if ($item->inventory_consumed_at) {
            $item->forceFill(['inventory_consumed_at' => null])->save();
        }
    }

    /**
     * @param  Collection<int, RecipeItem>  $recipeItems
     */
    private function consumeRecipeLines($recipeItems, OrderItem $item): void
    {
        foreach ($recipeItems as $recipeItem) {
            $baseQuantity = $this->baseQuantityForLine($recipeItem, $item);

            $this->stockMovements->record(
                $recipeItem->inventoryItem,
                'sale_consumption',
                Quantity::multiply($baseQuantity, '-1'),
                // Historical cost snapshot for COGS reporting — the average
                // cost will move on as new purchases are received.
                unitCost: $recipeItem->inventoryItem->fresh()->average_cost,
                reference: $item,
            );
        }
    }

    private function baseQuantityForLine(RecipeItem $recipeItem, OrderItem $item): string
    {
        $perUnit = Quantity::convert((string) $recipeItem->quantity, $recipeItem->unit, $recipeItem->inventoryItem->baseUnit);

        return Quantity::multiply($perUnit, (string) $item->quantity);
    }
}
