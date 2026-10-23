<?php

namespace App\Services\Reports;

use App\Models\OrderItem;
use App\Models\RecipeItem;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes COGS/profit per sold product. Deliberately done in PHP rather
 * than a single SQL query — recipe_items is polymorphic (Product AND
 * ProductOption) and needs the same base-unit conversion
 * RecipeConsumptionService applies at consumption time, which isn't
 * something a portable (MySQL + SQLite) SQL query can express cleanly.
 *
 * Sensitive: gated by the owner-only `reports.profit.view` permission at
 * the controller level, not here.
 */
class ProfitAnalyticsService
{
    private const EXCLUDED_FROM_SALES = ['cancelled', 'rejected'];

    /**
     * @return Collection<int, object{product_name: string, quantity: int, revenue: int, cogs: int, profit: int, has_recipe: bool}>
     */
    public function profitByProduct(Carbon $from, Carbon $to): Collection
    {
        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', self::EXCLUDED_FROM_SALES)
            ->select('order_items.*')
            ->with([
                'product.recipeItems.inventoryItem.baseUnit',
                'product.recipeItems.unit',
                'options.productOption.recipeItems.inventoryItem.baseUnit',
                'options.productOption.recipeItems.unit',
            ])
            ->get();

        return $items->groupBy('product_name_snapshot')->map(function (Collection $group, string $name) {
            $quantity = (int) $group->sum('quantity');
            $revenue = (int) $group->sum(fn (OrderItem $item) => $item->line_total - $item->discount_total);
            $hasRecipe = (bool) $group->first(fn (OrderItem $item) => $item->product && $item->product->recipeItems->isNotEmpty());

            $cogs = (int) round($group->sum(function (OrderItem $item) {
                $perUnitCost = $this->recipeCostPerUnit($item->product?->recipeItems ?? collect());

                foreach ($item->options as $orderItemOption) {
                    $perUnitCost += $this->recipeCostPerUnit($orderItemOption->productOption?->recipeItems ?? collect());
                }

                return $perUnitCost * $item->quantity;
            }));

            return (object) [
                'product_name' => $name,
                'quantity' => $quantity,
                'revenue' => $revenue,
                'cogs' => $cogs,
                'profit' => $revenue - $cogs,
                'has_recipe' => $hasRecipe,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, RecipeItem>  $recipeItems
     */
    private function recipeCostPerUnit(Collection $recipeItems): float
    {
        $cost = 0.0;

        foreach ($recipeItems as $recipeItem) {
            if (! $recipeItem->inventoryItem || $recipeItem->inventoryItem->average_cost === null) {
                continue;
            }

            $baseQuantity = Quantity::convert((string) $recipeItem->quantity, $recipeItem->unit, $recipeItem->inventoryItem->baseUnit);
            $cost += (float) $baseQuantity * $recipeItem->inventoryItem->average_cost;
        }

        return $cost;
    }
}
