<?php

namespace App\Services\Reports;

use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CategorySalesReportService
{
    private const EXCLUDED_FROM_SALES = ['cancelled', 'rejected'];

    /**
     * @return Collection<int, object{category_id: ?int, category_name: ?string, total_quantity: int, total_revenue: int}>
     */
    public function categorySales(Carbon $from, Carbon $to): Collection
    {
        // LEFT JOINs throughout so an item whose product (or the product's
        // category) was later deleted still counts toward sales totals —
        // it just falls into the null/"Uncategorized" group instead of
        // being silently dropped.
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', self::EXCLUDED_FROM_SALES)
            ->selectRaw('categories.id as category_id, categories.name as category_name, sum(order_items.quantity) as total_quantity, sum(order_items.line_total - order_items.discount_total) as total_revenue')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();
    }
}
