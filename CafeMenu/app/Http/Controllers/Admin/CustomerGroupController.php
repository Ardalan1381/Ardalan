<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerGroupRequest;
use App\Http\Requests\Admin\UpdateCustomerGroupRequest;
use App\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerGroupController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CustomerGroup::class);

        $customerGroups = CustomerGroup::query()->withCount('customers')->orderBy('name')->get();

        return view('admin.customer-groups.index', ['customerGroups' => $customerGroups]);
    }

    public function create(): View
    {
        $this->authorize('create', CustomerGroup::class);

        return view('admin.customer-groups.create');
    }

    public function store(StoreCustomerGroupRequest $request): RedirectResponse
    {
        CustomerGroup::create($request->validated());

        return redirect()->route('admin.customer-groups.index')->with('status', __('admin.flash.customer_group_created'));
    }

    public function edit(CustomerGroup $customerGroup): View
    {
        $this->authorize('update', $customerGroup);

        return view('admin.customer-groups.edit', ['customerGroup' => $customerGroup]);
    }

    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $customerGroup->update($request->validated());

        return redirect()->route('admin.customer-groups.index')->with('status', __('admin.flash.customer_group_updated'));
    }

    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        $this->authorize('delete', $customerGroup);

        if ($customerGroup->customers()->exists()) {
            return redirect()->route('admin.customer-groups.index')
                ->with('error', __('admin.flash.customer_group_delete_blocked'));
        }

        $customerGroup->delete();

        return redirect()->route('admin.customer-groups.index')->with('status', __('admin.flash.customer_group_deleted'));
    }
}
