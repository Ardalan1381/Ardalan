@extends('layouts.app')

@section('title', $business->name)

@section('content')
    @vite('resources/js/menu.js')

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 pb-16">
        <header class="relative overflow-hidden bg-linear-to-b from-primary/10 via-primary/5 to-gray-50 dark:to-gray-950 px-4 pb-8 pt-4 text-center">
            <div class="mx-auto max-w-5xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-theme-toggle />
                        <x-locale-switcher />
                    </div>

                    @if ($business->ordering_enabled)
                        <a href="{{ route('cart.index') }}" class="relative inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-900 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.94-4.693 2.436-7.152.093-.464-.278-.898-.75-.898H5.106M7.5 14.25 5.106 5.272M6 18.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                            </svg>
                            {{ __('menu.cart') }}
                            <span data-cart-count hidden class="absolute -top-1.5 -end-1.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white"></span>
                        </a>
                    @endif
                </div>

                @if ($business->logo())
                    <img src="{{ $business->logo()->url() }}" alt="{{ $business->name }}" class="mx-auto mt-2 h-20 w-20 rounded-full border-4 border-white object-cover shadow-md">
                @endif
                <h1 class="mt-3 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $business->name }}</h1>
                @if ($business->address)
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $business->address }}</p>
                @endif
            </div>
        </header>

        @unless ($business->ordering_enabled)
            <div class="mx-auto max-w-5xl px-4">
                <div class="-mt-4 rounded-xl bg-warning-subtle px-4 py-3 text-center text-sm font-medium text-warning shadow-sm">
                    {{ __('menu.ordering_disabled_banner') }}
                </div>
            </div>
        @endunless

        @unless ($business->currencyFor()['is_home'])
            <div class="mx-auto max-w-5xl px-4">
                <div class="-mt-4 rounded-xl bg-gray-100 dark:bg-gray-900 px-4 py-2.5 text-center text-xs text-gray-500 dark:text-gray-400 shadow-sm">
                    {{ __('menu.reference_price_note', ['currency' => $business->currency]) }}
                </div>
            </div>
        @endunless

        @if ($table)
            <div class="mx-auto max-w-5xl px-4">
                <div class="-mt-4 flex items-center justify-between gap-3 rounded-xl bg-white dark:bg-gray-900 px-4 py-3 text-sm font-medium text-primary shadow-sm">
                    <div>
                        {{ __('menu.ordering_for_section', ['table' => $table->name, 'section' => $table->section->name]) }}
                        @if ($table->status === 'out_of_service')
                            <span class="block text-xs font-normal text-danger">{{ __('menu.table_out_of_service') }}</span>
                        @endif
                    </div>
                    <button
                        type="button"
                        id="call-waiter-button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 2.25 14.28 2.25 12Z" />
                        </svg>
                        <span>{{ __('menu.call_waiter') }}</span>
                    </button>
                </div>
            </div>
        @endif

        <div class="sticky top-0 z-10 mt-4 bg-gray-50/95 dark:bg-gray-950/95 px-4 py-3 backdrop-blur">
            <div class="mx-auto max-w-5xl space-y-3">
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="pointer-events-none absolute start-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                    </svg>
                    <input
                        id="menu-search"
                        type="search"
                        placeholder="{{ __('menu.search_placeholder') }}"
                        class="block w-full rounded-full border-0 bg-white dark:bg-gray-900 py-2.5 ps-10 pe-4 text-sm shadow-sm ring-1 ring-gray-200 dark:ring-gray-700 focus:outline-none focus:ring-2 focus:ring-primary/40"
                    >
                </div>

                @if ($categories->count() > 1)
                    <nav class="flex gap-2 overflow-x-auto pb-1">
                        @foreach ($categories as $category)
                            <a
                                href="#cat-{{ $category->id }}"
                                data-category-link
                                class="shrink-0 rounded-full border border-transparent bg-white dark:bg-gray-900 px-4 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-400 shadow-sm transition"
                            >
                                {{ $category->translate('name') }}
                            </a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>

        <div class="mx-auto max-w-5xl px-4">
            @foreach ($categories as $category)
                <section id="cat-{{ $category->id }}" data-category-section class="scroll-mt-36 py-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $category->translate('name') }}</h2>
                    @if ($category->translate('description'))
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $category->translate('description') }}</p>
                    @endif

                    @if ($category->products->isNotEmpty())
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                            @foreach ($category->products as $product)
                                @include('menu._product-card', ['product' => $product])
                            @endforeach
                        </div>
                    @endif

                    @foreach ($category->children as $child)
                        @if ($child->products->isNotEmpty())
                            <div class="mt-6">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">{{ $child->translate('name') }}</h3>
                                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                    @foreach ($child->products as $product)
                                        @include('menu._product-card', ['product' => $product])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </section>
            @endforeach

            @if ($categories->isEmpty())
                <x-empty-state :title="__('menu.empty')" class="my-16 border-0 bg-white dark:bg-gray-900 shadow-sm" />
            @endif
        </div>

        <footer class="px-4">
            <div class="mx-auto mt-6 max-w-5xl rounded-xl bg-white dark:bg-gray-900 p-6 text-center text-sm text-gray-500 dark:text-gray-400 shadow-sm">
                @if ($business->contact_phone)
                    <p class="font-medium text-gray-700 dark:text-gray-300">{{ $business->contact_phone }}</p>
                @endif

                @if (! empty($business->social_links))
                    <div class="mt-3 flex justify-center gap-4">
                        @foreach ($business->social_links as $platform => $url)
                            @if ($url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="capitalize text-primary hover:underline">{{ $platform }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if (! empty($business->working_hours))
                    <details class="mt-4 text-start">
                        <summary class="cursor-pointer text-center font-medium text-gray-700 dark:text-gray-300">{{ __('menu.working_hours') }}</summary>
                        <ul class="mt-3 space-y-1.5">
                            @php $dayNames = __('menu.days'); @endphp
                            @foreach ($business->working_hours as $day)
                                <li class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5 last:border-0">
                                    <span>{{ $dayNames[$day['day']] ?? '' }}</span>
                                    <span>{{ ($day['is_closed'] ?? false) ? __('menu.closed') : ($day['opens_at'] ?? '').' - '.($day['closes_at'] ?? '') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        </footer>
    </div>
@endsection
