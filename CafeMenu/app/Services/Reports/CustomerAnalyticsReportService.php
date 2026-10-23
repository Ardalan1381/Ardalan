<?php

namespace App\Services\Reports;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerAnalyticsReportService
{
    private const EXCLUDED_FROM_SALES = ['cancelled', 'rejected'];

    /**
     * @return Collection<int, object{customer_id: int, customer_name: string, order_count: int, total_spent: int}>
     */
    public function topCustomers(Carbon $from, Carbon $to, int $limit = 20): Collection
    {
        return Order::query()
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', self::EXCLUDED_FROM_SALES)
            ->selectRaw('customers.id as customer_id, customers.name as customer_name, count(*) as order_count, sum(orders.grand_total) as total_spent')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();
    }
}
