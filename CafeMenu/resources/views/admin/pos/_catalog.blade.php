{{-- Shared product browser used by both admin.pos.table and
     admin.pos.counter. Expects $categories (top-level Category models with
     products/children/children.products/optionGroups.options eager-loaded,
     per PosController::catalogForPos()). --}}
@php
    // A flat product_id => {name, unitPrice, options} map, embedded as JSON
    // so pos.js can rebuild the in-page cart from old('items') after a
    // validation error round-trip, without needing another request.
    $posCatalogMap = [];
    $collectPosProduct = function ($product) use (&$posCatalogMap) {
        $options = [];
        foreach ($product->optionGroups as $group) {
            foreach ($group->options as $option) {
                $options[$option->id] = [
                    'name' => $option->translate('name'),
                    'priceDelta' => $option->price_delta,
                ];
            }
        }

        $posCatalogMap[$product->id] = [
            'name' => $product->translate('name'),
            'unitPrice' => $product->effectivePrice(),
            'options' => $options,
        ];
    };

    foreach ($categories as $category) {
        foreach ($category->products as $product) {
            $collectPosProduct($product);
        }
        foreach ($category->children as $child) {
            foreach ($child->products as $product) {
                $collectPosProduct($product);
            }
        }
    }
@endphp

<script type="application/json" id="pos-catalog-data">@json($posCatalogMap, JSON_FORCE_OBJECT)</script>
<script type="application/json" id="pos-old-items">@json(old('items', []))</script>

<div data-pos-catalog>
    <input
        type="text"
        id="pos-product-search"
        placeholder="{{ __('admin.pos.search_products_placeholder') }}"
        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm focus:border-primary focus:ring-primary/30"
    >

    @if ($categories->isEmpty())
        <x-empty-state :title="__('admin.pos.no_products')" class="mt-4 border-0" />
    @else
        <nav class="mt-4 flex gap-2 overflow-x-auto pb-1">
            @foreach ($categories as $category)
                <a href="#pos-cat-{{ $category->id }}" class="shrink-0 rounded-full border border-gray-200 dark:border-gray-700 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                    {{ $category->translate('name') }}
                </a>
            @endforeach
        </nav>

        <div class="mt-4 max-h-[65vh] space-y-6 overflow-y-auto pe-1">
            @foreach ($categories as $category)
                <section id="pos-cat-{{ $category->id }}" class="scroll-mt-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $category->translate('name') }}</h3>

                    @if ($category->products->isNotEmpty())
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($category->products as $product)
                                @include('admin.pos._product', ['product' => $product])
                            @endforeach
                        </div>
                    @endif

                    @foreach ($category->children as $child)
                        @if ($child->products->isNotEmpty())
                            <div class="mt-3">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $child->translate('name') }}</h4>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    @foreach ($child->products as $product)
                                        @include('admin.pos._product', ['product' => $product])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
</div>
