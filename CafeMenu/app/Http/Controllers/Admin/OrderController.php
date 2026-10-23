<?php

namespace App\Http\Controllers\Admin;

use App\Actions\TransitionOrderStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\Printer;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $orders = Order::query()
            ->with('table')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.orders.index', ['orders' => $orders, 'statusFilter' => $request->string('status')->toString()]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load([
            'items.options',
            'table.section',
            'tableSession',
            'statusHistory.changedBy',
            'payments.recordedBy',
            'payments.refunds.processedBy',
            'payments.refunds.resolvedBy',
            'billSplits.parts.items.orderItem',
            'billSplits.parts.payments',
            'invoice',
            'printJobs.printer',
        ]);

        $billSplit = $order->billSplits->last();
        $activePrinters = Printer::active()->orderBy('name')->get();

        return view('admin.orders.show', ['order' => $order, 'billSplit' => $billSplit, 'activePrinters' => $activePrinters]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, TransitionOrderStatusAction $action): RedirectResponse
    {
        try {
            $action->execute($order, $request->validated('status'), $request->user(), $request->validated('note'));
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.show', $order)->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.flash.order_status_updated'));
    }
}
