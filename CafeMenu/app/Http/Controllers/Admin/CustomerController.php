<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\SystemSetting;
use App\Services\Customers\CustomerStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $search = $request->string('search')->toString();

        $customers = Customer::query()
            ->with('group')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(SystemSetting::get('pagination_per_page', 20))
            ->withQueryString();

        return view('admin.customers.index', ['customers' => $customers, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('admin.customers.create', ['groups' => CustomerGroup::orderBy('name')->get()]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::create([
            ...$request->validated(),
            'status' => 'active',
        ]);

        return redirect()->route('admin.customers.index')->with('status', __('admin.flash.customer_created'));
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('admin.customers.edit', [
            'customer' => $customer,
            'groups' => CustomerGroup::orderBy('name')->get(),
            'stats' => app(CustomerStatsService::class)->forCustomer($customer),
            'recentOrders' => $customer->orders()->latest()->limit(10)->get(),
            'loyaltyEntries' => $customer->loyaltyLedgerEntries()->latest()->limit(10)->get(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('admin.customers.index')->with('status', __('admin.flash.customer_updated'));
    }

    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $this->authorize('deactivate', $customer);

        $customer->update(['status' => $customer->status === 'active' ? 'blocked' : 'active']);

        return redirect()->route('admin.customers.index')->with('status', __('admin.flash.customer_status_updated'));
    }
}
