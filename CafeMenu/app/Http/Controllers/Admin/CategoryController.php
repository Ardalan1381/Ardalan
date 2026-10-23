<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()->orderBy('sort_order')->get();

        return view('admin.categories.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create', ['categories' => Category::orderBy('name')->get()]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return redirect()->route('admin.categories.index')->with('status', __('admin.flash.category_created'));
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        $category->load('media');
        $categories = Category::where('id', '!=', $category->id)->orderBy('name')->get();

        return view('admin.categories.edit', ['category' => $category, 'categories' => $categories]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        $category->update($data);
        $category->syncTranslations($translations);

        return redirect()->route('admin.categories.index')->with('status', __('admin.flash.category_updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->children()->exists() || $category->products()->exists()) {
            return redirect()->route('admin.categories.index')
                ->with('error', __('admin.flash.category_delete_blocked'));
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', __('admin.flash.category_deleted'));
    }
}
