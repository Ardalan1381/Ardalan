<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Catalog\SyncRecipeItemsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Requests\Admin\UpdateRecipeItemsRequest;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()->with('category')->orderBy('sort_order')->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.products.index', ['products' => $products]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.create', ['categories' => Category::orderBy('name')->get()]);
    }

    public function store(StoreProductRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $product = Product::create($request->validated());

        $auditLogger->log('product.created', $product, new: $product->only(['name', 'category_id', 'base_price']));

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.product_created'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load([
            'optionGroups.options.recipeItems.inventoryItem',
            'optionGroups.options.recipeItems.unit',
            'recipeItems.inventoryItem',
            'recipeItems.unit',
            'media',
            'prices',
        ]);

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'inventoryItems' => InventoryItem::active()->orderBy('name')->get(),
            'inventoryUnits' => InventoryUnit::orderBy('name')->get(),
            'activeCurrencies' => $this->activeDisplayCurrencies(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, AuditLogger $auditLogger): RedirectResponse
    {
        $original = $product->only(['name', 'category_id', 'base_price', 'discount_price', 'is_active', 'is_available']);

        $data = $request->validated();
        $prices = $data['prices'] ?? [];
        $translations = $data['translations'] ?? [];
        unset($data['prices'], $data['translations']);

        $product->update($data);
        $this->syncPrices($product, $prices);
        $product->syncTranslations($translations);

        $auditLogger->log(
            'product.updated',
            $product,
            old: $original,
            new: $product->only(['name', 'category_id', 'base_price', 'discount_price', 'is_active', 'is_available']),
        );

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.product_updated'));
    }

    /**
     * @return Collection<string, array{code: string, symbol: ?string, minor_units: int}>
     */
    private function activeDisplayCurrencies(): Collection
    {
        return collect(BusinessSetting::current()->display_currencies ?? [])
            ->filter(fn (array $currency) => $currency['is_active'] ?? false);
    }

    /**
     * @param  array<string, array{price?: string, discount_price?: string}>  $prices
     */
    private function syncPrices(Product $product, array $prices): void
    {
        $activeLocales = $this->activeDisplayCurrencies()->keys();

        foreach ($prices as $locale => $entry) {
            // A price for a currency that's since been disabled/removed from
            // business settings is left alone rather than silently deleted —
            // re-enabling that currency later should not require re-typing
            // every product's price for it.
            if (! $activeLocales->contains($locale)) {
                continue;
            }

            $price = $entry['price'] ?? null;

            if ($price === null || $price === '') {
                $product->prices()->where('locale', $locale)->delete();

                continue;
            }

            $product->prices()->updateOrCreate(
                ['locale' => $locale],
                ['price' => (int) $price, 'discount_price' => filled($entry['discount_price'] ?? null) ? (int) $entry['discount_price'] : null],
            );
        }
    }

    public function destroy(Product $product, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('delete', $product);

        $auditLogger->log('product.deleted', $product, old: $product->only(['name', 'category_id']));

        $product->delete();

        return redirect()->route('admin.products.index')->with('status', __('admin.flash.product_deleted'));
    }

    public function updateRecipe(UpdateRecipeItemsRequest $request, Product $product, SyncRecipeItemsAction $action): RedirectResponse
    {
        $action->execute($product, $request->linesForAction());

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.recipe_updated'));
    }
}
