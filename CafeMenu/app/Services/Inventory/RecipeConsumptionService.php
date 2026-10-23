<?php

namespace App\Services\Inventory;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RecipeItem;
use App\Services\AuditLogger;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * Deducts (or gives back) inventory when an order is confirmed/cancelled.
 * Recipe lines are resolved from the CURRENT live product/option — not the
 * order's price snapshot — since only the current recipe should be
 * consumed; a later recipe edit should not retroactively change what an
 * already-placed order consumed.
 */
class RecipeConsumptionService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function consume(Order $order): void
    {
        if ($order->inventory_consumed_at) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items()->with(['product.recipeItems.inventoryItem.baseUnit', 'product.recipeItems.unit', 'options.productOption.recipeItems.inventoryItem.baseUnit', 'options.productOption.recipeItems.unit'])->get() as $item) {
                if (! $item->product) {
                    $this->auditLogger->log('inventory.consumption_skipped', $order, new: [
                        'order_item_id' => $item->id,
                        'reason' => 'product no longer exists',
                    ]);

                    continue;
                }

                $this->consumeRecipeLines($item->product->recipeItems, $item);

                foreach ($item->options as $orderItemOption) {
                    if (! $orderItemOption->productOption) {
                        continue;
                    }

                    $this->consumeRecipeLines($orderItemOption->productOption->recipeItems, $item);
                }
            }

            $order->update(['inventory_consumed_at' => now()]);
        });
    }

    public function reverse(Order $order): void
    {
        if (! $order->inventory_consumed_at) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items()->with(['product.recipeItems.inventoryItem.baseUnit', 'product.recipeItems.unit', 'options.productOption.recipeItems.inventoryItem.baseUnit', 'options.productOption.recipeItems.unit'])->get() as $item) {
                if (! $item->product) {
                    continue;
                }

                $this->reverseRecipeLines($item->product->recipeItems, $item);

                foreach ($item->options as $orderItemOption) {
                    if (! $orderItemOption->productOption) {
                        continue;
                    }

                    $this->reverseRecipeLines($orderItemOption->productOption->recipeItems, $item);
                }
            }

            $order->update(['inventory_consumed_at' => null]);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, RecipeItem>  $recipeItems
     */
    private function consumeRecipeLines($recipeItems, OrderItem $item): void
    {
        foreach ($recipeItems as $recipeItem) {
            $baseQuantity = $this->baseQuantityForLine($recipeItem, $item);

            $this->stockMovements->record(
                $recipeItem->inventoryItem,
                'sale_consumption',
                '-'.$baseQuantity,
                reference: $item,
            );
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, RecipeItem>  $recipeItems
     */
    private function reverseRecipeLines($recipeItems, OrderItem $item): void
    {
        foreach ($recipeItems as $recipeItem) {
            $baseQuantity = $this->baseQuantityForLine($recipeItem, $item);

            $this->stockMovements->record(
                $recipeItem->inventoryItem,
                'sale_reversal',
                $baseQuantity,
                reference: $item,
                allowNegative: true,
            );
        }
    }

    private function baseQuantityForLine(RecipeItem $recipeItem, OrderItem $item): string
    {
        $perUnit = Quantity::convert((string) $recipeItem->quantity, $recipeItem->unit, $recipeItem->inventoryItem->baseUnit);

        return Quantity::multiply($perUnit, (string) $item->quantity);
    }
}
