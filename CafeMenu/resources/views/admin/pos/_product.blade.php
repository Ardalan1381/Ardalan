{{-- One product row in the POS catalog: a summary button that expands an
     inline panel (option groups, quantity, notes, "add to order"). Mirrors
     the option-group markup/data-attributes menu/show.blade.php + product.js
     use, so the same min/max/required validation logic applies here. --}}
<div
    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-3"
    data-pos-product
    data-product-name="{{ strtolower($product->translate('name')) }}"
>
    <button type="button" class="flex w-full items-center justify-between gap-3 text-start" data-pos-product-toggle>
        <span class="min-w-0">
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $product->translate('name') }}</span>
            @if ($product->label)
                <span class="ms-1 inline-block rounded-full bg-secondary/10 px-1.5 py-0.5 text-[10px] font-medium text-secondary align-middle">
                    {{ __('admin.products.label_'.$product->label) }}
                </span>
            @endif
        </span>
        <span class="shrink-0 text-sm font-semibold text-gray-700 dark:text-gray-300">
            {{ \App\Support\Money::format($product->effectivePrice()) }}
        </span>
    </button>

    <div
        hidden
        class="mt-3 space-y-3 border-t border-gray-200 dark:border-gray-700 pt-3"
        data-pos-product-panel
        data-product-id="{{ $product->id }}"
        data-product-display-name="{{ $product->translate('name') }}"
        data-unit-price="{{ $product->effectivePrice() }}"
    >
        @foreach ($product->optionGroups as $group)
            <fieldset data-option-group data-group-name="{{ $group->translate('name') }}" data-min="{{ $group->min_select }}" data-max="{{ $group->max_select }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <legend class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $group->translate('name') }}</legend>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                        {{ $group->is_required ? __('menu.required') : __('menu.optional') }}
                        &middot;
                        {{ $group->selection_type === 'single' ? __('menu.choose_one') : ($group->max_select ? __('menu.choose_up_to', ['max' => $group->max_select]) : __('menu.choose_up_to_any')) }}
                    </span>
                </div>
                <div class="mt-1.5 space-y-1">
                    @foreach ($group->options as $option)
                        <label class="flex cursor-pointer items-center justify-between gap-2 text-xs">
                            <span class="flex items-center gap-1.5">
                                <input
                                    type="{{ $group->selection_type === 'single' ? 'radio' : 'checkbox' }}"
                                    name="pos-product-{{ $product->id }}-group-{{ $group->id }}"
                                    value="{{ $option->id }}"
                                    data-option-name="{{ $option->translate('name') }}"
                                    data-price-delta="{{ $option->price_delta }}"
                                    class="h-3.5 w-3.5 border-gray-300 dark:border-gray-600 text-primary focus:ring-2 focus:ring-primary/30"
                                >
                                {{ $option->translate('name') }}
                            </span>
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">
                                @if ($option->price_delta > 0)
                                    +{{ \App\Support\Money::format($option->price_delta) }}
                                @elseif ($option->price_delta < 0)
                                    -{{ \App\Support\Money::format(abs($option->price_delta)) }}
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <p data-pos-option-error hidden class="rounded-lg bg-danger-subtle px-2 py-1.5 text-xs text-danger"></p>

        <textarea
            data-pos-item-notes
            rows="1"
            placeholder="{{ __('menu.notes_placeholder') }}"
            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 text-xs shadow-sm focus:border-primary focus:ring-primary/30"
        ></textarea>

        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5">
                <button type="button" data-pos-qty-decrement class="flex h-6 w-6 items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400">
                    &minus;
                </button>
                <input type="number" data-pos-qty value="1" min="1" max="50" class="w-10 rounded-lg border-gray-300 dark:border-gray-600 p-1 text-center text-xs">
                <button type="button" data-pos-qty-increment class="flex h-6 w-6 items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400">
                    +
                </button>
            </div>
            <button type="button" data-pos-add-to-order class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90">
                {{ __('admin.pos.add_to_order') }}
                <span data-pos-line-total></span>
            </button>
        </div>
    </div>
</div>
