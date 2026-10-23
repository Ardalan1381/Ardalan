<?php

namespace App\Services\Customers;

use App\Models\Customer;

/**
 * Read-only, on-demand stats for a single customer's admin detail page.
 * Deliberately not cached/denormalized — this is a low-traffic admin view,
 * not a reporting dashboard.
 */
class CustomerStatsService
{
    // Same convention as App\Services\ReportService::EXCLUDED_FROM_SALES —
    // inlined here rather than shared, since this service has its own
    // per-customer scope and no reason to depend on ReportService.
    private const EXCLUDED_FROM_SALES = ['cancelled', 'rejected'];

    /**
     * @return array{total_spent: int, order_count: int, average_order_value: int, last_order_at: ?\Illuminate\Support\Carbon}
     */
    public function forCustomer(Customer $customer): array
    {
        $orderCount = $customer->orders()->count();

        $salesRow = $customer->orders()
            ->whereNotIn('status', self::EXCLUDED_FROM_SALES)
            ->selectRaw('sum(grand_total) as total, count(*) as order_count')
            ->first();

        $totalSpent = (int) ($salesRow->total ?? 0);
        $salesOrderCount = (int) ($salesRow->order_count ?? 0);

        $lastOrder = $customer->orders()->latest('created_at')->first();

        return [
            'total_spent' => $totalSpent,
            'order_count' => $orderCount,
            'average_order_value' => $salesOrderCount > 0 ? intdiv($totalSpent, $salesOrderCount) : 0,
            'last_order_at' => $lastOrder?->created_at,
        ];
    }
}
