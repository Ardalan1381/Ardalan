<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Http\Requests\Admin\UpdateDiscountRequest;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Discount;
use App\Models\Product;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiscountController extends Controller
{
    private const PIVOT_KEYS = ['product_ids', 'category_ids', 'customer_ids', 'customer_group_ids'];

    public function index(): View
    {
        $this->authorize('viewAny', Discount::class);

        $discounts = Discount::query()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.discounts.index', ['discounts' => $discounts]);
    }

    public function create(): View
    {
        $this->authorize('create', Discount::class);

        return view('admin.discounts.create', $this->formOptions());
    }

    public function store(StoreDiscountRequest $request): RedirectResponse
    {
        $discount = Discount::create([
            ...collect($request->validated())->except(self::PIVOT_KEYS)->all(),
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->syncPivots($discount, $request->validated());

        return redirect()->route('admin.discounts.index')->with('status', __('admin.flash.discount_created'));
    }

    public function edit(Discount $discount): View
    {
        $this->authorize('update', $discount);

        $discount->load(['products', 'categories', 'customers', 'customerGroups']);

        return view('admin.discounts.edit', ['discount' => $discount, ...$this->formOptions()]);
    }

    public function update(UpdateDiscountRequest $request, Discount $discount): RedirectResponse
    {
        $discount->update(collect($request->validated())->except(self::PIVOT_KEYS)->all());

        $this->syncPivots($discount, $request->validated());

        return redirect()->route('admin.discounts.index')->with('status', __('admin.flash.discount_updated'));
    }

    public function destroy(Discount $discount): RedirectResponse
    {
        $this->authorize('delete', $discount);

        if ($discount->used_count > 0) {
            return redirect()->route('admin.discounts.index')
                ->with('error', __('admin.flash.discount_delete_blocked'));
        }

        $discount->delete();

        return redirect()->route('admin.discounts.index')->with('status', __('admin.flash.discount_deleted'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPivots(Discount $discount, array $data): void
    {
        $discount->products()->sync($data['product_ids'] ?? []);
        $discount->categories()->sync($data['category_ids'] ?? []);
        $discount->customers()->sync($data['customer_ids'] ?? []);
        $discount->customerGroups()->sync($data['customer_group_ids'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'products' => Product::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
            'campaigns' => Campaign::orderBy('name')->get(),
        ];
    }
}
