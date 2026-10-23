<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\RecordPurchaseReturnAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseReturnRequest;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $returns = PurchaseReturn::query()
            ->with(['supplier', 'purchase', 'items.inventoryItem'])
            ->latest()
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.purchase-returns.index', ['returns' => $returns]);
    }

    public function create(): View
    {
        $this->authorize('create', PurchaseReturn::class);

        return view('admin.purchase-returns.create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'purchases' => Purchase::orderByDesc('id')->limit(200)->get(),
            'items' => InventoryItem::active()->orderBy('name')->get(),
        ]);
    }

    public function store(StorePurchaseReturnRequest $request, RecordPurchaseReturnAction $action): RedirectResponse
    {
        try {
            $action->execute($request->validated(), $request->validated('lines'), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.purchase-returns.create')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.purchase-returns.index')->with('status', __('admin.flash.purchase_return_created'));
    }
}
