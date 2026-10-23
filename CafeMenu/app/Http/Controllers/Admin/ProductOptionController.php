<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Catalog\SyncRecipeItemsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOptionRequest;
use App\Http\Requests\Admin\UpdateOptionRequest;
use App\Http\Requests\Admin\UpdateRecipeItemsRequest;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use Illuminate\Http\RedirectResponse;

class ProductOptionController extends Controller
{
    public function store(StoreOptionRequest $request, Product $product, ProductOptionGroup $optionGroup): RedirectResponse
    {
        abort_unless($optionGroup->product_id === $product->id, 404);

        $optionGroup->options()->create($request->validated());

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.option_added'));
    }

    public function update(UpdateOptionRequest $request, Product $product, ProductOptionGroup $optionGroup, ProductOption $option): RedirectResponse
    {
        abort_unless($optionGroup->product_id === $product->id, 404);
        abort_unless($option->product_option_group_id === $optionGroup->id, 404);

        $data = $request->validated();
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        $option->update($data);
        $option->syncTranslations($translations);

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.option_updated'));
    }

    public function destroy(Product $product, ProductOptionGroup $optionGroup, ProductOption $option): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($optionGroup->product_id === $product->id, 404);
        abort_unless($option->product_option_group_id === $optionGroup->id, 404);

        $option->delete();

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.option_removed'));
    }

    public function updateRecipe(
        UpdateRecipeItemsRequest $request,
        Product $product,
        ProductOptionGroup $optionGroup,
        ProductOption $option,
        SyncRecipeItemsAction $action
    ): RedirectResponse {
        abort_unless($optionGroup->product_id === $product->id, 404);
        abort_unless($option->product_option_group_id === $optionGroup->id, 404);

        $action->execute($option, $request->linesForAction());

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.recipe_updated'));
    }
}
