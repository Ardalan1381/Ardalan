<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\RecordStockAdjustmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockAdjustmentRequest;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $adjustments = StockAdjustment::query()
            ->with(['items.inventoryItem', 'createdBy'])
            ->latest()
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.stock-adjustments.index', ['adjustments' => $adjustments]);
    }

    public function create(): View
    {
        $this->authorize('create', StockAdjustment::class);

        return view('admin.stock-adjustments.create', [
            'items' => InventoryItem::active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request, RecordStockAdjustmentAction $action): RedirectResponse
    {
        try {
            $action->execute($request->validated(), $request->linesForAction(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.stock-adjustments.create')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.stock-adjustments.index')->with('status', __('admin.flash.stock_adjustment_created'));
    }
}
