<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with([
                'media',
                'products' => fn ($query) => $query->active()->available()->orderBy('sort_order')->with(['media', 'prices']),
                'children' => fn ($query) => $query->active()->orderBy('sort_order'),
                'children.products' => fn ($query) => $query->active()->available()->orderBy('sort_order')->with(['media', 'prices']),
            ])
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty()
                || $category->children->contains(fn (Category $child) => $child->products->isNotEmpty()))
            ->values();

        return view('menu.index', [
            'categories' => $categories,
            'business' => BusinessSetting::current(),
            'table' => $this->currentTable(),
        ]);
    }

    public function table(string $qrToken): RedirectResponse
    {
        $table = Table::query()->active()->where('qr_token', $qrToken)->firstOrFail();

        session(['dining_table_id' => $table->id]);

        return redirect()->route('menu.index');
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'media',
            'category',
            'prices',
            'optionGroups' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            'optionGroups.options' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
        ]);

        return view('menu.show', [
            'product' => $product,
            'business' => BusinessSetting::current(),
            'table' => $this->currentTable(),
        ]);
    }

    public function cart(): View
    {
        return view('cart.index', [
            'business' => BusinessSetting::current(),
            'table' => $this->currentTable(),
        ]);
    }

    private function currentTable(): ?Table
    {
        $tableId = session('dining_table_id');

        if (! $tableId) {
            return null;
        }

        return Table::query()->active()->with('section')->find($tableId);
    }
}
