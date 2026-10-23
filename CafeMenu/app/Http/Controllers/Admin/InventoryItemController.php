<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventoryItemRequest;
use App\Http\Requests\Admin\UpdateInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturnItem;
use App\Models\RecipeItem;
use App\Models\StockAdjustmentItem;
use App\Models\StockCountItem;
use App\Models\SystemSetting;
use App\Models\WasteRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', InventoryItem::class);

        $items = InventoryItem::query()
            ->with(['category', 'baseUnit'])
            ->orderBy('name')
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.inventory.items.index', ['items' => $items]);
    }

    public function create(): View
    {
        $this->authorize('create', InventoryItem::class);

        return view('admin.inventory.items.create', $this->formOptions());
    }

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        InventoryItem::create($request->validated());

        return redirect()->route('admin.inventory.items.index')->with('status', __('admin.flash.inventory_item_created'));
    }

    public function edit(InventoryItem $item): View
    {
        $this->authorize('update', $item);

        return view('admin.inventory.items.edit', ['item' => $item, ...$this->formOptions()]);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $item->update($request->validated());

        return redirect()->route('admin.inventory.items.index')->with('status', __('admin.flash.inventory_item_updated'));
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        if ($this->hasHistory($item)) {
            $item->update(['is_active' => false]);

            return redirect()->route('admin.inventory.items.index')->with('status', __('admin.flash.inventory_item_deactivated'));
        }

        $item->delete();

        return redirect()->route('admin.inventory.items.index')->with('status', __('admin.flash.inventory_item_deleted'));
    }

    /**
     * Every one of these tables restricts deleting the referenced inventory
     * item at the database level, so a hard delete must be refused (in
     * favour of deactivation) whenever any of them has a row for it.
     */
    private function hasHistory(InventoryItem $item): bool
    {
        return $item->movements()->exists()
            || PurchaseItem::where('inventory_item_id', $item->id)->exists()
            || StockAdjustmentItem::where('inventory_item_id', $item->id)->exists()
            || WasteRecord::where('inventory_item_id', $item->id)->exists()
            || StockCountItem::where('inventory_item_id', $item->id)->exists()
            || PurchaseReturnItem::where('inventory_item_id', $item->id)->exists()
            || RecipeItem::where('inventory_item_id', $item->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => InventoryCategory::orderBy('name')->get(),
            'units' => InventoryUnit::orderBy('name')->get(),
        ];
    }
}
