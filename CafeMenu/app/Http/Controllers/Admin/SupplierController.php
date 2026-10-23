<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::orderBy('name')->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.suppliers.index', ['suppliers' => $suppliers]);
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('admin.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return redirect()->route('admin.suppliers.index')->with('status', __('admin.flash.supplier_created'));
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('admin.suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('admin.suppliers.index')->with('status', __('admin.flash.supplier_updated'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchases()->exists() || PurchaseReturn::where('supplier_id', $supplier->id)->exists()) {
            $supplier->update(['is_active' => false]);

            return redirect()->route('admin.suppliers.index')->with('status', __('admin.flash.supplier_deactivated'));
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('status', __('admin.flash.supplier_deleted'));
    }
}
