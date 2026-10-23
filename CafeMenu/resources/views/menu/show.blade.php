@extends('layouts.app')

@section('title', $product->translate('name').' - '.$business->name)

@section('content')
    @vite('resources/js/product.js')

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 pb-28">
        <div class="flex items-center justify-between bg-white dark:bg-gray-900 px-4 py-3 shadow-sm">
            <a href="{{ route('menu.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 rtl:rotate-180">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __('menu.back_to_menu_short') }}
            </a>
            <div class="flex items-center gap-2">
                <x-theme-toggle />
                <x-locale-switcher />
                @if ($business->ordering_enabled)
                    <a href="{{ route('cart.index') }}" class="relative text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('menu.cart') }}
                        <span data-cart-count hidden class="ms-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs text-white"></span>
                    </a>
                @endif
            </div>
        </div>

        @if ($table)
            <div class="bg-primary/5 px-4 py-2 text-center text-sm font-medium text-primary">
                {{ __('menu.ordering_for', ['table' => $table->name]) }}
            </div>
        @endif

        <div class="mx-auto max-w-2xl">
            @if ($product->media->isNotEmpty())
                <div class="flex snap-x gap-2 overflow-x-auto bg-white dark:bg-gray-900 p-2 sm:rounded-b-2xl">
                    @foreach ($product->media as $media)
                        <img src="{{ $media->url() }}" alt="{{ $product->translate('name') }}" class="aspect-4/3 w-full shrink-0 snap-center rounded-xl object-cover">
                    @endforeach
                </div>
            @else
                <div class="flex aspect-4/3 w-full items-center justify-center bg-white dark:bg-gray-900 text-gray-200 dark:text-gray-700 sm:rounded-b-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="h-16 w-16">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5 8 11l3.5 3.5L16 10l5 6.5M3 6h18v12H3z" />
                    </svg>
                </div>
            @endif

            <div class="-mt-4 rounded-t-2xl bg-white dark:bg-gray-900 px-4 pb-4 pt-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $product->translate('name') }}</h1>
                    @if ($product->label)
                        <span class="shrink-0 rounded-full bg-secondary/10 px-2.5 py-1 text-xs font-medium text-secondary">
                            {{ __('admin.products.label_'.$product->label) }}
                        </span>
                    @endif
                </div>

                @php $price = $product->priceFor($business) @endphp
                <p class="mt-2 text-lg font-bold text-primary">
                    {{ \App\Support\Money::formatAs($price['amount'], $price['currency_code'], $price['minor_units'], $price['currency_symbol']) }}
                    @if ($price['original_amount'])
                        <span class="ms-1 text-sm font-normal text-gray-400 dark:text-gray-500 line-through">{{ \App\Support\Money::formatAs($price['original_amount'], $price['currency_code'], $price['minor_units'], $price['currency_symbol']) }}</span>
                    @endif
                </p>
                @unless ($price['is_home'])
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('menu.reference_price_note', ['currency' => $business->currency]) }}</p>
                @endunless

                @if ($product->translate('description'))
                    <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ $product->translate('description') }}</p>
                @endif
            </div>

            <div class="px-4">
            @if (! $business->ordering_enabled)
                <p class="mt-4 inline-block rounded-lg bg-warning-subtle px-3 py-2 text-sm font-medium text-warning">{{ __('menu.ordering_disabled') }}</p>
            @elseif (! $product->is_available)
                <p class="mt-4 inline-block rounded-lg bg-danger-subtle px-3 py-2 text-sm font-medium text-danger">{{ __('menu.product_unavailable') }}</p>
            @else
                @if ($product->optionGroups->isNotEmpty())
                    <div class="mt-4 space-y-4">
                        @foreach ($product->optionGroups as $group)
                            <fieldset
                                data-option-group
                                data-group-name="{{ $group->translate('name') }}"
                                data-min="{{ $group->min_select }}"
                                data-max="{{ $group->max_select }}"
                                class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm"
                            >
                                <div class="flex items-center justify-between">
                                    <legend class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $group->translate('name') }}</legend>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $group->is_required ? __('menu.required') : __('menu.optional') }}
                                        &middot; {{ $group->selection_type === 'single' ? __('menu.choose_one') : ($group->max_select ? __('menu.choose_up_to', ['max' => $group->max_select]) : __('menu.choose_up_to_any')) }}
                                    </span>
                                </div>
                                <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($group->options as $option)
                                        <label class="flex cursor-pointer items-center justify-between py-2.5 text-sm">
                                            <span class="flex items-center gap-2.5">
                                                <input
                                                    type="{{ $group->selection_type === 'single' ? 'radio' : 'checkbox' }}"
                                                    name="group-{{ $group->id }}"
                                                    value="{{ $option->id }}"
                                                    data-option-name="{{ $option->translate('name') }}"
                                                    data-price-delta="{{ $option->price_delta }}"
                                                    class="h-4 w-4 border-gray-300 dark:border-gray-600 text-primary focus:ring-2 focus:ring-primary/30"
                                                >
                                                {{ $option->translate('name') }}
                                            </span>
                                            <span class="text-gray-500 dark:text-gray-400">
                                                @if ($option->price_delta > 0)
                                                    +{{ \App\Support\Money::format($option->price_delta) }}
                                                @elseif ($option->price_delta < 0)
                                                    -{{ \App\Support\Money::format(abs($option->price_delta)) }}
                                                @else
                                                    &mdash;
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                @endif

                <p id="option-error" hidden class="mt-4 rounded-lg bg-danger-subtle px-3 py-2 text-sm text-danger"></p>

                <div class="mt-4 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <label for="item-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('menu.notes_optional') }}</label>
                    <textarea id="item-notes" rows="2" class="mt-1.5 block w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm focus:border-primary focus:ring-primary/30" placeholder="{{ __('menu.notes_placeholder') }}"></textarea>

                    <div class="mt-4 flex items-center justify-between">
                        <label for="quantity" class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('menu.quantity') }}</label>
                        <input id="quantity" type="number" value="1" min="1" max="50" class="w-20 rounded-lg border-gray-300 dark:border-gray-600 text-center text-sm shadow-sm focus:border-primary focus:ring-primary/30">
                    </div>
                </div>
            @endif
            </div>
        </div>
    </div>

    @if ($business->ordering_enabled && $product->is_available)
        <div class="fixed inset-x-0 bottom-0 border-t border-gray-100 dark:border-gray-800 bg-white/95 dark:bg-gray-900/95 p-4 shadow-[0_-4px_16px_rgba(0,0,0,0.06)] backdrop-blur">
            <button
                id="add-to-cart"
                data-product-id="{{ $product->id }}"
                data-product-slug="{{ $product->slug }}"
                data-product-name="{{ $product->translate('name') }}"
                data-unit-price="{{ $product->effectivePrice() }}"
                class="mx-auto flex w-full max-w-2xl items-center justify-between rounded-xl bg-primary px-5 py-3.5 text-sm font-semibold text-white shadow-md transition hover:opacity-90"
            >
                <span>{{ __('menu.add_to_cart') }}</span>
                <span id="live-total"></span>
            </button>
        </div>
    @endif
@endsection
