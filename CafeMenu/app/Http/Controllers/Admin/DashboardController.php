<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ReportService $reports): View
    {
        $this->authorize('dashboard.view');

        return view('admin.dashboard', [
            'todaySales' => $reports->todaySales(),
            'todayOrderCount' => $reports->todayOrderCount(),
            'todayAverageOrderValue' => $reports->todayAverageOrderValue(),
            'activeOrdersCount' => $reports->activeOrdersCount(),
            'popularProducts' => $reports->popularProducts(Carbon::today(), Carbon::now(), 5),
        ]);
    }
}
