<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOptionGroupRequest;
use App\Http\Requests\Admin\UpdateOptionGroupRequest;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use Illuminate\Http\RedirectResponse;

class ProductOptionGroupController extends Controller
{
    public function store(StoreOptionGroupRequest $request, Product $product): RedirectResponse
    {
        $product->optionGroups()->create($request->validated());

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.option_group_added'));
    }

    public function update(UpdateOptionGroupRequest $request, Product $product, ProductOptionGroup $optionGroup): RedirectResponse
    {
        abort_unless($optionGroup->product_id === $product->id, 404);

        $data = $request->validated();
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        $optionGroup->update($data);
        $optionGroup->syncTranslations($translations);

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.option_group_updated'));
    }

    public function destroy(Product $product, ProductOptionGroup $optionGroup): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($optionGroup->product_id === $product->id, 404);

        $optionGroup->delete();

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.option_group_removed'));
    }
}
