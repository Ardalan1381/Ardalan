<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\CreatePurchaseAction;
use App\Actions\Inventory\MarkPurchasePaidAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceivePurchaseRequest;
use App\Http\Requests\Admin\StorePurchaseRequest;
use App\Http\Requests\Admin\UpdatePurchasePaymentStatusRequest;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Purchase::class);

        $purchases = Purchase::query()
            ->with('supplier')
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.purchases.index', ['purchases' => $purchases]);
    }

    public function create(): View
    {
        $this->authorize('create', Purchase::class);

        return view('admin.purchases.create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'items' => InventoryItem::active()->orderBy('name')->get(),
            'units' => InventoryUnit::orderBy('name')->get(),
        ]);
    }

    public function store(StorePurchaseRequest $request, CreatePurchaseAction $action): RedirectResponse
    {
        $purchase = $action->execute($request->validated(), $request->user());

        return redirect()->route('admin.purchases.show', $purchase)->with('status', __('admin.flash.purchase_created'));
    }

    public function show(Purchase $purchase): View
    {
        $this->authorize('view', $purchase);

        $purchase->load(['supplier', 'items.inventoryItem.baseUnit', 'items.unit', 'createdBy']);

        return view('admin.purchases.show', ['purchase' => $purchase]);
    }

    public function receive(ReceivePurchaseRequest $request, Purchase $purchase, ReceivePurchaseAction $action): RedirectResponse
    {
        try {
            $action->execute($purchase, $request->validated('receipts'), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.purchases.show', $purchase)->withErrors($e->errors());
        }

        return redirect()->route('admin.purchases.show', $purchase)->with('status', __('admin.flash.purchase_received'));
    }

    public function updatePaymentStatus(UpdatePurchasePaymentStatusRequest $request, Purchase $purchase, MarkPurchasePaidAction $action): RedirectResponse
    {
        $action->execute($purchase, $request->validated('payment_status'));

        return redirect()->route('admin.purchases.show', $purchase)->with('status', __('admin.flash.purchase_payment_status_updated'));
    }
}
