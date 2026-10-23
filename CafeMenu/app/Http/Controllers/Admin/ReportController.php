<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\Reports\CategorySalesReportService;
use App\Services\Reports\CustomerAnalyticsReportService;
use App\Services\Reports\InventoryAnalyticsService;
use App\Services\Reports\ProfitAnalyticsService;
use App\Services\Reports\StaffPerformanceReportService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(
        Request $request,
        ReportService $reports,
        CategorySalesReportService $categorySales,
        StaffPerformanceReportService $staffPerformance,
        InventoryAnalyticsService $inventoryAnalytics,
        CustomerAnalyticsReportService $customerAnalytics,
        ProfitAnalyticsService $profitAnalytics,
    ): View {
        $this->authorize('reports.view');

        // The period is chosen in the business's own timezone ("today" in
        // Tehran is not "today" in UTC), then converted to the UTC instants
        // timestamps are stored in. $displayFrom/$displayTo stay local for
        // the date inputs.
        $timezone = BusinessSetting::current()->timezone ?: config('app.timezone');
        $displayFrom = $request->filled('from')
            ? Carbon::parse((string) $request->string('from'), $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfDay()->subDays(6);
        $displayTo = $request->filled('to')
            ? Carbon::parse((string) $request->string('to'), $timezone)->endOfDay()
            : Carbon::now($timezone);
        $from = $displayFrom->copy()->utc();
        $to = $displayTo->copy()->utc();

        $canViewProfit = $request->user()->hasPermission('reports.profit.view');

        return view('admin.reports.index', [
            'from' => $displayFrom,
            'to' => $displayTo,
            'salesByPeriod' => $reports->salesByPeriod($from, $to),
            'popularProducts' => $reports->popularProducts($from, $to, 20),
            'tableSales' => $reports->tableSales($from, $to),
            'paymentSummary' => $reports->paymentSummary($from, $to),
            'categorySales' => $categorySales->categorySales($from, $to),
            'staffOrderPerformance' => $staffPerformance->staffOrderPerformance($from, $to),
            'staffPaymentVolume' => $staffPerformance->staffPaymentVolume($from, $to),
            'closedShifts' => $staffPerformance->closedShifts($from, $to),
            'stockValuation' => $inventoryAnalytics->stockValuation(),
            'wasteCost' => $inventoryAnalytics->wasteCost($from, $to),
            'lowStockCount' => $inventoryAnalytics->lowStockCount(),
            'purchaseSpendBySupplier' => $inventoryAnalytics->purchaseSpendBySupplier($from, $to),
            'topCustomers' => $customerAnalytics->topCustomers($from, $to),
            'canViewProfit' => $canViewProfit,
            'profitByProduct' => $canViewProfit ? $profitAnalytics->profitByProduct($from, $to) : null,
            'profitSummary' => $canViewProfit ? $profitAnalytics->summary($from, $to) : null,
        ]);
    }
}
