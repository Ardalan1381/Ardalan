@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $product->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.products.update', $product) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-select name="category_id" :label="__('admin.fields.category')" required>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select>

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $product->name) }}" required />

            <x-textarea name="description" :label="__('common.labels.description')" rows="3">{{ old('description', $product->description) }}</x-textarea>

            <x-input
                name="base_price"
                type="number"
                :label="__('admin.products.base_price')"
                value="{{ old('base_price', $product->base_price) }}"
                min="0"
                required
                :hint="__('admin.products.base_price_hint', ['currency' => \App\Models\BusinessSetting::current()->currency, 'example' => \App\Support\Money::format(1234)])"
            />

            <x-input name="discount_price" type="number" :label="__('admin.products.discount_price')" value="{{ old('discount_price', $product->discount_price) }}" min="0" />

            @if ($activeCurrencies->isNotEmpty())
                <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.products.other_currency_prices') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.products.other_currency_prices_hint') }}</p>

                    @foreach ($activeCurrencies as $locale => $currency)
                        @php $override = $product->prices->firstWhere('locale', $locale); @endphp
                        <div class="grid grid-cols-2 gap-3">
                            <x-input
                                name="prices[{{ $locale }}][price]"
                                type="number"
                                min="0"
                                :label="($currency['symbol'] ?? $currency['code']).' — '.__('admin.products.base_price')"
                                value="{{ old("prices.$locale.price", $override->price ?? '') }}"
                            />
                            <x-input
                                name="prices[{{ $locale }}][discount_price]"
                                type="number"
                                min="0"
                                :label="($currency['symbol'] ?? $currency['code']).' — '.__('admin.products.discount_price')"
                                value="{{ old("prices.$locale.discount_price", $override->discount_price ?? '') }}"
                            />
                        </div>
                    @endforeach
                </div>
            @else
                <p class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-4 text-xs text-gray-500 dark:text-gray-400">
                    {!! __('admin.products.no_currencies_configured', ['link' => '<a href="'.route('admin.settings.business.edit').'" class="text-primary hover:underline">'.__('admin.products.no_currencies_configured_link').'</a>']) !!}
                </p>
            @endif

            <x-translation-fields :model="$product" :fields="['name' => __('admin.fields.name'), 'description' => __('common.labels.description')]" />

            <x-select name="label" :label="__('admin.products.label')">
                <option value="">{{ __('admin.products.label_none') }}</option>
                <option value="new" @selected(old('label', $product->label) === 'new')>{{ __('admin.products.label_new') }}</option>
                <option value="popular" @selected(old('label', $product->label) === 'popular')>{{ __('admin.products.label_popular') }}</option>
                <option value="special" @selected(old('label', $product->label) === 'special')>{{ __('admin.products.label_special') }}</option>
            </x-select>

            <x-input name="sort_order" type="number" :label="__('admin.fields.sort_order')" value="{{ old('sort_order', $product->sort_order) }}" min="0" />

            <x-checkbox
                name="is_available"
                :label="__('admin.fields.available')"
                :hint="__('admin.products.available_hint')"
                value="1"
                :checked="old('is_available', $product->is_available)"
                data-confirm-uncheck="{{ __('admin.products.confirm_hide_from_menu') }}"
            />

            <x-checkbox
                name="is_active"
                :label="__('common.labels.active')"
                :hint="__('admin.products.active_hint')"
                value="1"
                :checked="old('is_active', $product->is_active)"
                data-confirm-uncheck="{{ __('admin.products.confirm_hide_from_menu') }}"
            />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>

    <x-card class="mt-6 max-w-2xl" :title="__('admin.products.images')">
        <div class="flex flex-wrap gap-4">
            @foreach ($product->media as $media)
                <div class="w-32">
                    <img src="{{ $media->url() }}" alt="" class="h-32 w-32 rounded-lg border border-gray-200 dark:border-gray-700 object-cover">
                    <p class="mt-1 text-xs {{ $media->is_primary ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">
                        {{ $media->is_primary ? __('admin.media.primary') : '' }}
                    </p>
                    <div class="mt-1 flex gap-2 text-xs">
                        @unless ($media->is_primary)
                            <form method="POST" action="{{ route('admin.products.media.primary', [$product, $media]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.media.make_primary') }}</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('admin.products.media.destroy', [$product, $media]) }}" data-confirm="{{ __('admin.media.confirm_remove') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-danger hover:underline">{{ __('common.actions.remove') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.products.media.store', $product) }}" enctype="multipart/form-data" class="mt-4">
            @csrf
            <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
            <x-button type="submit" size="sm" class="ms-2">{{ __('admin.media.upload') }}</x-button>
        </form>
    </x-card>

    <div class="mt-6 max-w-2xl">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.option_groups.title') }}</h2>

        <div class="mt-4 space-y-6">
            @foreach ($product->optionGroups as $group)
                <x-card>
                    <form method="POST" action="{{ route('admin.products.option-groups.update', [$product, $group]) }}" class="grid grid-cols-2 gap-3 text-sm">
                        @csrf
                        @method('PUT')

                        <div class="col-span-2">
                            <x-input name="name" :label="__('admin.fields.name')" value="{{ $group->name }}" required />
                        </div>

                        <x-select name="selection_type" :label="__('admin.option_groups.selection_type')">
                            <option value="single" @selected($group->selection_type === 'single')>{{ __('admin.option_groups.single') }}</option>
                            <option value="multiple" @selected($group->selection_type === 'multiple')>{{ __('admin.option_groups.multiple') }}</option>
                        </x-select>

                        <x-input name="sort_order" type="number" :label="__('admin.fields.sort_order')" value="{{ $group->sort_order }}" min="0" />

                        <x-input name="min_select" type="number" :label="__('admin.option_groups.min_select')" value="{{ $group->min_select }}" min="0" />

                        <x-input name="max_select" type="number" :label="__('admin.option_groups.max_select')" value="{{ $group->max_select }}" min="1" />

                        <x-checkbox name="is_required" :label="__('admin.option_groups.required')" value="1" :checked="$group->is_required" />

                        <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="$group->is_active" />

                        <div class="col-span-2">
                            <x-translation-fields :model="$group" :fields="['name' => __('admin.fields.name')]" />
                        </div>

                        <div class="col-span-2">
                            <x-button type="submit" size="sm">{{ __('admin.option_groups.save_group') }}</x-button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.products.option-groups.destroy', [$product, $group]) }}" data-confirm="{{ __('admin.option_groups.confirm_delete_group') }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-danger hover:underline">{{ __('admin.option_groups.delete_group') }}</button>
                    </form>

                    <div class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('admin.option_groups.options') }}</p>

                        <div class="mt-2 space-y-2">
                            @foreach ($group->options as $option)
                                <div class="border-b border-gray-100 dark:border-gray-800 pb-2 text-sm">
                                    <div class="flex flex-wrap items-end gap-2">
                                        <form method="POST" action="{{ route('admin.products.option-groups.options.update', [$product, $group, $option]) }}" class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="name" value="{{ $option->name }}" class="w-40 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                            <input type="number" name="price_delta" value="{{ $option->price_delta }}" class="w-24 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                            <input type="number" name="sort_order" value="{{ $option->sort_order }}" min="0" class="w-16 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                            <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                                <input type="checkbox" name="is_active" value="1" @checked($option->is_active)>
                                                {{ __('common.labels.active') }}
                                            </label>
                                            <button type="submit" class="text-xs text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.option_groups.save') }}</button>

                                            <details class="w-full">
                                                <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400 hover:underline">{{ __('admin.translations.title') }}</summary>
                                                <div class="mt-2">
                                                    <x-translation-fields :model="$option" :fields="['name' => __('admin.fields.name')]" />
                                                </div>
                                            </details>
                                        </form>
                                        <form method="POST" action="{{ route('admin.products.option-groups.options.destroy', [$product, $group, $option]) }}" data-confirm="{{ __('admin.option_groups.confirm_delete_option') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-danger hover:underline">{{ __('admin.option_groups.delete') }}</button>
                                        </form>
                                    </div>

                                    @can('update', $product)
                                        <details class="mt-2">
                                            <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400 hover:underline">
                                                {{ __('admin.inventory.recipe_title') }} ({{ $option->recipeItems->count() }})
                                            </summary>
                                            <div class="mt-2 ps-3 border-s border-gray-100 dark:border-gray-800">
                                                <x-recipe-editor
                                                    :recipable="$option"
                                                    :action="route('admin.products.option-groups.options.recipe.update', [$product, $group, $option])"
                                                    :items="$inventoryItems"
                                                    :units="$inventoryUnits"
                                                />
                                            </div>
                                        </details>
                                    @endcan
                                </div>
                            @endforeach
                        </div>

                        <form method="POST" action="{{ route('admin.products.option-groups.options.store', [$product, $group]) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.option_groups.new_option_name') }}</label>
                                <input type="text" name="name" required class="mt-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.option_groups.price_delta') }}</label>
                                <input type="number" name="price_delta" value="0" required class="mt-1 w-24 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                            </div>
                            <x-button type="submit" size="sm">{{ __('admin.option_groups.add_option') }}</x-button>
                        </form>
                    </div>
                </x-card>
            @endforeach
        </div>

        <x-card class="mt-6 max-w-md">
            <form method="POST" action="{{ route('admin.products.option-groups.store', $product) }}" class="grid grid-cols-2 gap-3 text-sm">
                @csrf
                <div class="col-span-2">
                    <x-input name="name" :label="__('admin.option_groups.new_group_name')" required />
                </div>
                <x-select name="selection_type" :label="__('admin.option_groups.selection_type')">
                    <option value="single">{{ __('admin.option_groups.single') }}</option>
                    <option value="multiple">{{ __('admin.option_groups.multiple') }}</option>
                </x-select>
                <x-input name="min_select" type="number" :label="__('admin.option_groups.min_select')" value="0" min="0" />
                <div class="col-span-2">
                    <x-button type="submit" size="sm">{{ __('admin.option_groups.add_group') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>

    <x-card class="mt-6 max-w-2xl" :title="__('admin.inventory.recipe_title')">
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.inventory.recipe_hint') }}</p>
        <div class="mt-3">
            <x-recipe-editor
                :recipable="$product"
                :action="route('admin.products.recipe.update', $product)"
                :items="$inventoryItems"
                :units="$inventoryUnits"
            />
        </div>
    </x-card>
@endsection
