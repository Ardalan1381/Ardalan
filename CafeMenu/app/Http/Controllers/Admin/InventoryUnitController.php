<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventoryUnitRequest;
use App\Http\Requests\Admin\UpdateInventoryUnitRequest;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\PurchaseItem;
use App\Models\RecipeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryUnitController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', InventoryUnit::class);

        $units = InventoryUnit::orderBy('type')->orderBy('name')->get();

        return view('admin.inventory.units.index', ['units' => $units]);
    }

    public function create(): View
    {
        $this->authorize('create', InventoryUnit::class);

        return view('admin.inventory.units.create');
    }

    public function store(StoreInventoryUnitRequest $request): RedirectResponse
    {
        InventoryUnit::create($request->validated());

        return redirect()->route('admin.inventory.units.index')->with('status', __('admin.flash.inventory_unit_created'));
    }

    public function edit(InventoryUnit $unit): View
    {
        $this->authorize('update', $unit);

        return view('admin.inventory.units.edit', ['unit' => $unit]);
    }

    public function update(UpdateInventoryUnitRequest $request, InventoryUnit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        return redirect()->route('admin.inventory.units.index')->with('status', __('admin.flash.inventory_unit_updated'));
    }

    public function destroy(InventoryUnit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        // inventory_units has no is_active column to fall back to, so —
        // like CategoryController::destroy — a unit still referenced
        // anywhere is simply blocked from deletion rather than deactivated.
        if ($this->isReferenced($unit)) {
            return redirect()->route('admin.inventory.units.index')
                ->with('error', __('admin.flash.inventory_unit_delete_blocked'));
        }

        $unit->delete();

        return redirect()->route('admin.inventory.units.index')->with('status', __('admin.flash.inventory_unit_deleted'));
    }

    private function isReferenced(InventoryUnit $unit): bool
    {
        return InventoryItem::where('base_unit_id', $unit->id)->exists()
            || PurchaseItem::where('unit_id', $unit->id)->exists()
            || RecipeItem::where('unit_id', $unit->id)->exists();
    }
}
