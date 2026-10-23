<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Direct aggregation queries against the existing transactional tables —
 * no separate reporting/warehouse database, per project scope. Relies on
 * the indexes already on orders(status, created_at), order_items(order_id),
 * and payments(order_id, status).
 */
class ReportService
{
    private const EXCLUDED_FROM_SALES = ['cancelled', 'rejected'];

    public function todaySales(): int
    {
        return (int) Order::query()
            ->whereDate('created_at', today())
            ->whereNotIn('status', self::EXCLUDED_FROM_SALES)
            ->sum('grand_total');
    }

    public function todayOrderCount(): int
    {
        return Order::query()->whereDate('created_at', today())->count();
    }

    public function todayAverageOrderValue(): int
    {
        $row = Order::query()
            ->whereDate('created_at', today())
            ->whereNotIn('status', self::EXCLUDED_FROM_SALES)
            ->selectRaw('sum(grand_total) as total, count(*) as order_count')
            ->first();

        return $row->order_count > 0 ? intdiv((int) $row->total, $row->order_count) : 0;
    }

    public function activeOrdersCount(): int
    {
        return Order::query()->whereIn('status', Order::ACTIVE_STATUSES)->count();
    }

    /**
     * @return Collection<int, object{product_name_snapshot: string, total_quantity: int, total_revenue: int}>
     */
    public function popularProducts(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', self::EXCLUDED_FROM_SALES)
            ->selectRaw('order_items.product_name_snapshot, sum(order_items.quantity) as total_quantity, sum(order_items.line_total) as total_revenue')
            ->groupBy('order_items.product_name_snapshot')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object{date: string, total: int, order_count: int}>
     */
    public function salesByPeriod(Carbon $from, Carbon $to): Collection
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', self::EXCLUDED_FROM_SALES)
            ->selectRaw('date(created_at) as date, sum(grand_total) as total, count(*) as order_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * @return Collection<int, object{table_name: string, total: int, order_count: int}>
     */
    public function tableSales(Carbon $from, Carbon $to): Collection
    {
        return Order::query()
            ->join('tables', 'tables.id', '=', 'orders.table_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', self::EXCLUDED_FROM_SALES)
            ->selectRaw('tables.name as table_name, sum(orders.grand_total) as total, count(*) as order_count')
            ->groupBy('tables.id', 'tables.name')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * @return Collection<int, object{method: string, status: string, total: int, payment_count: int}>
     */
    public function paymentSummary(Carbon $from, Carbon $to): Collection
    {
        return Payment::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('method, status, sum(amount) as total, count(*) as payment_count')
            ->groupBy('method', 'status')
            ->orderBy('method')
            ->get();
    }
}
