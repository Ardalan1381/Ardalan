<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CompleteStockCountAction;
use App\Actions\Inventory\StartStockCountAction;
use App\Actions\Inventory\SubmitStockCountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartStockCountRequest;
use App\Http\Requests\Admin\SubmitStockCountRequest;
use App\Models\InventoryItem;
use App\Models\StockCount;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockCountController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', StockCount::class);

        $counts = StockCount::query()
            ->withCount('items')
            ->latest()
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.stock-counts.index', ['counts' => $counts]);
    }

    public function create(): View
    {
        $this->authorize('create', StockCount::class);

        return view('admin.stock-counts.create', [
            'items' => InventoryItem::active()->orderBy('name')->get(),
        ]);
    }

    public function store(StartStockCountRequest $request, StartStockCountAction $action): RedirectResponse
    {
        $count = $action->execute($request->validated('inventory_item_ids'), $request->user());

        return redirect()->route('admin.stock-counts.show', $count)->with('status', __('admin.flash.stock_count_started'));
    }

    public function show(StockCount $count): View
    {
        $this->authorize('view', $count);

        $count->load('items.inventoryItem.baseUnit');

        return view('admin.stock-counts.show', ['count' => $count]);
    }

    public function submit(SubmitStockCountRequest $request, StockCount $count, SubmitStockCountAction $action): RedirectResponse
    {
        try {
            $action->execute($count, $request->countedLinesForAction());
        } catch (ValidationException $e) {
            return redirect()->route('admin.stock-counts.show', $count)->withErrors($e->errors());
        }

        return redirect()->route('admin.stock-counts.show', $count)->with('status', __('admin.flash.stock_count_updated'));
    }

    public function complete(Request $request, StockCount $count, CompleteStockCountAction $action): RedirectResponse
    {
        $this->authorize('complete', $count);

        try {
            $action->execute($count, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.stock-counts.show', $count)->withErrors($e->errors());
        }

        return redirect()->route('admin.stock-counts.show', $count)->with('status', __('admin.flash.stock_count_completed'));
    }
}
