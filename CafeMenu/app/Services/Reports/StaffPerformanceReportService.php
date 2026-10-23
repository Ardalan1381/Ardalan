<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffPerformanceReportService
{
    private const EXCLUDED_FROM_SALES = ['cancelled', 'rejected'];

    /**
     * @return Collection<int, object{user_id: int, user_name: string, order_count: int, total_revenue: int}>
     */
    public function staffOrderPerformance(Carbon $from, Carbon $to): Collection
    {
        return Order::query()
            ->join('users', 'users.id', '=', 'orders.waiter_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', self::EXCLUDED_FROM_SALES)
            ->selectRaw('users.id as user_id, users.name as user_name, count(*) as order_count, sum(orders.grand_total) as total_revenue')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    /**
     * @return Collection<int, object{user_id: int, user_name: string, payment_count: int, total_amount: int}>
     */
    public function staffPaymentVolume(Carbon $from, Carbon $to): Collection
    {
        return Payment::query()
            ->join('users', 'users.id', '=', 'payments.recorded_by_user_id')
            ->whereBetween('payments.created_at', [$from, $to])
            ->where('payments.status', 'paid')
            ->selectRaw('users.id as user_id, users.name as user_name, count(*) as payment_count, sum(payments.amount) as total_amount')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_amount')
            ->get();
    }

    /**
     * @return Collection<int, Shift>
     */
    public function closedShifts(Carbon $from, Carbon $to): Collection
    {
        return Shift::query()
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$from, $to])
            ->with('user')
            ->orderByDesc('closed_at')
            ->get();
    }
}
