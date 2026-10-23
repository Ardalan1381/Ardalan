<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Order;
use Illuminate\View\View;

class KitchenDisplayController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Order::class);

        $threshold = BusinessSetting::current()->kitchen_delay_threshold_minutes;

        $orders = Order::query()
            ->with(['items.options', 'table.section'])
            ->whereIn('status', Order::ACTIVE_STATUSES)
            ->oldest('status_changed_at')
            ->get()
            ->groupBy('status');

        $columns = collect(Order::ACTIVE_STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => $orders->get($status, collect())]);

        return view('admin.kitchen.index', ['columns' => $columns, 'threshold' => $threshold]);
    }
}
