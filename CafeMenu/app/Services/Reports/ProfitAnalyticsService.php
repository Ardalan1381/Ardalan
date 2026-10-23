<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RecipeItem;
use App\Models\Refund;
use App\Models\StockMovement;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes COGS/profit per sold product.
 *
 * Reporting semantics (see docs/engineering/DECISIONS.md, D-03):
 * - A sale is REALISED when its order is 'completed'. Pending, in-progress,
 *   cancelled and rejected orders are not revenue here (the operational
 *   sales report keeps its broader "booked" view).
 * - Revenue is line total minus line discount, in integer minor units.
 * - COGS is what the order's items actually consumed: the 'sale_consumption'
 *   stock movements (net of reversals) priced at the unit cost recorded on
 *   each movement at consumption time. Only when that history is missing
 *   (orders consumed before costs were recorded, or never consumed) is the
 *   current recipe × current average cost used, and the row is flagged
 *   cogs_estimated so the screen can say so.
 * - Refunds are money given back, not a product attribute: completed
 *   refunds of the period's realised orders are reported in summary(),
 *   pending ones separately (not yet deducted).
 * - Money is integer; quantity × cost uses bcmath and is rounded once per
 *   row, never accumulated in floating point.
 *
 * Sensitive: gated by the owner-only `reports.profit.view` permission at
 * the controller level, not here.
 */
class ProfitAnalyticsService
{
    public const REALISED_STATUSES = ['completed'];

    /**
     * @return Collection<int, object{product_name: string, quantity: int, revenue: int, cogs: int, profit: int, has_recipe: bool, cogs_estimated: bool}>
     */
    public function profitByProduct(Carbon $from, Carbon $to): Collection
    {
        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', self::REALISED_STATUSES)
            ->select('order_items.*')
            ->with([
                'product.recipeItems.inventoryItem.baseUnit',
                'product.recipeItems.unit',
                'options.productOption.recipeItems.inventoryItem.baseUnit',
                'options.productOption.recipeItems.unit',
            ])
            ->get();

        $movementsByItem = StockMovement::query()
            ->where('reference_type', (new OrderItem)->getMorphClass())
            ->whereIn('reference_id', $items->pluck('id'))
            ->whereIn('type', ['sale_consumption', 'sale_reversal'])
            ->get(['reference_id', 'quantity', 'unit_cost'])
            ->groupBy('reference_id');

        return $items->groupBy('product_name_snapshot')->map(function (Collection $group, string $name) use ($movementsByItem) {
            $cogs = '0';
            $estimated = false;

            foreach ($group as $item) {
                [$itemCost, $itemEstimated] = $this->itemCost($item, $movementsByItem->get($item->id, collect()));
                $cogs = bcadd($cogs, $itemCost, 6);
                $estimated = $estimated || $itemEstimated;
            }

            $quantity = (int) $group->sum('quantity');
            $revenue = (int) $group->sum(fn (OrderItem $item) => $item->line_total - $item->discount_total);
            $cogsMinor = $this->roundMoney($cogs);

            return (object) [
                'product_name' => $name,
                'quantity' => $quantity,
                'revenue' => $revenue,
                'cogs' => $cogsMinor,
                'profit' => $revenue - $cogsMinor,
                'has_recipe' => (bool) $group->first(fn (OrderItem $item) => $item->product && $item->product->recipeItems->isNotEmpty()),
                'cogs_estimated' => $estimated,
            ];
        })->values();
    }

    /**
     * @return array{revenue: int, refunds: int, net_revenue: int, pending_refunds: int, cogs: int, gross_profit: int, cogs_estimated: bool}
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $rows = $this->profitByProduct($from, $to);
        $realisedOrderIds = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', self::REALISED_STATUSES)
            ->select('id');

        $refunds = (int) Refund::query()->whereIn('order_id', $realisedOrderIds)->where('status', 'completed')->sum('amount');
        $pendingRefunds = (int) Refund::query()->whereIn('order_id', $realisedOrderIds)->where('status', 'pending')->sum('amount');
        $revenue = (int) $rows->sum('revenue');
        $cogs = (int) $rows->sum('cogs');

        return [
            'revenue' => $revenue,
            'refunds' => $refunds,
            'net_revenue' => $revenue - $refunds,
            'pending_refunds' => $pendingRefunds,
            'cogs' => $cogs,
            'gross_profit' => $revenue - $refunds - $cogs,
            'cogs_estimated' => (bool) $rows->contains('cogs_estimated', true),
        ];
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     * @return array{0: string, 1: bool} cost in minor units (decimal string), whether it is an estimate
     */
    private function itemCost(OrderItem $item, Collection $movements): array
    {
        if ($movements->isNotEmpty() && $movements->every(fn (StockMovement $m) => $m->unit_cost !== null)) {
            $cost = '0';
            foreach ($movements as $movement) {
                // Consumption is stored negative; cost is the positive amount.
                $cost = bcsub($cost, bcmul((string) $movement->quantity, (string) $movement->unit_cost, 6), 6);
            }

            return [$cost, false];
        }

        $perUnit = $this->recipeCostPerUnit($item->product?->recipeItems ?? collect());
        foreach ($item->options as $orderItemOption) {
            $perUnit = bcadd($perUnit, $this->recipeCostPerUnit($orderItemOption->productOption?->recipeItems ?? collect()), 6);
        }

        // No recipe at all → genuinely zero cost, not an estimate.
        $hasRecipe = ($item->product && $item->product->recipeItems->isNotEmpty())
            || $item->options->contains(fn ($o) => $o->productOption && $o->productOption->recipeItems->isNotEmpty());

        return [bcmul($perUnit, (string) $item->quantity, 6), $hasRecipe];
    }

    /**
     * @param  Collection<int, RecipeItem>  $recipeItems
     */
    private function recipeCostPerUnit(Collection $recipeItems): string
    {
        $cost = '0';

        foreach ($recipeItems as $recipeItem) {
            if (! $recipeItem->inventoryItem || $recipeItem->inventoryItem->average_cost === null) {
                continue;
            }

            $baseQuantity = Quantity::convert((string) $recipeItem->quantity, $recipeItem->unit, $recipeItem->inventoryItem->baseUnit);
            $cost = bcadd($cost, bcmul($baseQuantity, (string) $recipeItem->inventoryItem->average_cost, 6), 6);
        }

        return $cost;
    }

    /** Half-up rounding of a non-negative-or-negative decimal string to an int. */
    private function roundMoney(string $amount): int
    {
        $half = str_starts_with($amount, '-') ? '-0.5' : '0.5';

        return (int) bcadd($amount, $half, 0);
    }
}
