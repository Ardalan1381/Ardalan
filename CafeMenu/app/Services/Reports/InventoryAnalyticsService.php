<?php

namespace App\Services\Reports;

use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\WasteRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InventoryAnalyticsService
{
    /**
     * @return array{total_value: int, item_count: int}
     */
    public function stockValuation(): array
    {
        $items = InventoryItem::query()->active()->get(['current_stock', 'average_cost']);

        $totalValue = $items->sum(fn (InventoryItem $item) => (float) $item->current_stock * ($item->average_cost ?? 0));

        return [
            'total_value' => (int) round($totalValue),
            'item_count' => $items->count(),
        ];
    }

    public function wasteCost(Carbon $from, Carbon $to): int
    {
        $waste = WasteRecord::query()->whereBetween('wasted_at', [$from, $to])->get(['quantity', 'unit_cost']);

        return (int) round($waste->sum(fn (WasteRecord $w) => (float) $w->quantity * ($w->unit_cost ?? 0)));
    }

    public function lowStockCount(): int
    {
        return InventoryItem::query()
            ->active()
            ->whereNotNull('min_stock_level')
            ->whereColumn('current_stock', '<=', 'min_stock_level')
            ->count();
    }

    /**
     * @return Collection<int, object{supplier_name: string, purchase_count: int, total_spend: int}>
     */
    public function purchaseSpendBySupplier(Carbon $from, Carbon $to): Collection
    {
        return Purchase::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereBetween('purchases.purchase_date', [$from, $to])
            ->selectRaw('suppliers.name as supplier_name, count(*) as purchase_count, sum(purchases.total_cost) as total_spend')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_spend')
            ->get();
    }
}
