<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventoryCategoryRequest;
use App\Http\Requests\Admin\UpdateInventoryCategoryRequest;
use App\Models\InventoryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', InventoryCategory::class);

        $categories = InventoryCategory::with('parent')->orderBy('name')->get();

        return view('admin.inventory.categories.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        $this->authorize('create', InventoryCategory::class);

        return view('admin.inventory.categories.create', ['categories' => InventoryCategory::orderBy('name')->get()]);
    }

    public function store(StoreInventoryCategoryRequest $request): RedirectResponse
    {
        InventoryCategory::create($request->validated());

        return redirect()->route('admin.inventory.categories.index')->with('status', __('admin.flash.inventory_category_created'));
    }

    public function edit(InventoryCategory $category): View
    {
        $this->authorize('update', $category);

        $categories = InventoryCategory::where('id', '!=', $category->id)->orderBy('name')->get();

        return view('admin.inventory.categories.edit', ['category' => $category, 'categories' => $categories]);
    }

    public function update(UpdateInventoryCategoryRequest $request, InventoryCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('admin.inventory.categories.index')->with('status', __('admin.flash.inventory_category_updated'));
    }

    public function destroy(InventoryCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        // inventory_categories has no is_active column, so — like
        // CategoryController::destroy — a category still in use is simply
        // blocked from deletion rather than deactivated.
        if ($category->children()->exists() || $category->items()->exists()) {
            return redirect()->route('admin.inventory.categories.index')
                ->with('error', __('admin.flash.inventory_category_delete_blocked'));
        }

        $category->delete();

        return redirect()->route('admin.inventory.categories.index')->with('status', __('admin.flash.inventory_category_deleted'));
    }
}
