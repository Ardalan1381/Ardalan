@extends('layouts.app')

@php
    $business = \App\Models\BusinessSetting::safe();
    // Operational full-width screens (KDS, POS) declare @section('focus-mode'):
    // the sidebar then stays a drawer up to 2xl instead of md, so a kitchen
    // or counter tablet gets the whole screen width.
    $focusMode = $__env->hasSection('focus-mode');
@endphp

@section('content')
    <div class="flex min-h-screen">
        <div id="admin-sidebar-backdrop" hidden @class(['fixed inset-0 z-30 bg-gray-900/50', 'md:hidden' => ! $focusMode, '2xl:hidden' => $focusMode])></div>

        <aside id="admin-sidebar" @class([
            'fixed inset-y-0 start-0 z-40 w-64 -translate-x-full overflow-y-auto border-e border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 transition-transform duration-200 rtl:translate-x-full',
            'md:static md:z-auto md:w-60 md:shrink-0 md:translate-x-0 rtl:md:translate-x-0' => ! $focusMode,
            '2xl:static 2xl:z-auto 2xl:w-60 2xl:shrink-0 2xl:translate-x-0 rtl:2xl:translate-x-0' => $focusMode,
        ])>
            <div class="mb-6 flex items-center gap-2.5 px-1">
                @if ($business->logo())
                    <img src="{{ $business->logo()->url() }}" alt="{{ $business->name }}" class="h-9 w-9 rounded-lg object-cover">
                @else
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3.75-6h.75m-.75 3h.75m-.75 3h.75M6 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                @endif
                <p class="min-w-0 flex-1 truncate text-sm font-bold text-gray-900 dark:text-gray-100">{{ $business->name ?: config('app.name') }}</p>
                <button id="admin-sidebar-close" type="button" aria-label="{{ __('nav.admin.close_menu') }}" @class(['shrink-0 rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800', 'md:hidden' => ! $focusMode, '2xl:hidden' => $focusMode])>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="text-sm">
                <div class="space-y-0.5">
                    <x-nav-link href="{{ route('admin.dashboard') }}" :active="request()->routeIs('admin.dashboard')">
                        <x-slot:icon>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                            </svg>
                        </x-slot:icon>
                        {{ __('nav.admin.dashboard') }}
                    </x-nav-link>
                </div>

                <x-nav-group :label="__('nav.admin.groups.operations')">
                    @can('create', \App\Models\Order::class)
                        <x-nav-link href="{{ route('admin.pos.index') }}" :active="request()->routeIs('admin.pos.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.625c.621 0 1.125.504 1.125 1.125V6h-.75a.75.75 0 0 1-.75-.75v-.75m0 0H3.75" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M4.5 6.75v10.5a1.5 1.5 0 0 0 1.5 1.5h4.5m3-12v12" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.pos') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', \App\Models\Order::class)
                        <x-nav-link href="{{ route('admin.orders.index') }}" :active="request()->routeIs('admin.orders.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.orders') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', \App\Models\Order::class)
                        <x-nav-link href="{{ route('admin.kitchen.index') }}" :active="request()->routeIs('admin.kitchen.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.kitchen') }}
                        </x-nav-link>
                    @endcan

                    @can('waiter_calls.view')
                        <x-nav-link href="{{ route('admin.waiter-calls.index') }}" :active="request()->routeIs('admin.waiter-calls.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 2.25 14.28 2.25 12Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.waiter_calls') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', \App\Models\Payment::class)
                        <x-nav-link href="{{ route('admin.payments.index') }}" :active="request()->routeIs('admin.payments.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m6 0h3.75m-16.5 4.5h13.5c1.036 0 1.875-.84 1.875-1.875V6.375c0-1.036-.84-1.875-1.875-1.875H3.375c-1.036 0-1.875.84-1.875 1.875v11.25c0 1.035.84 1.875 1.875 1.875Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.payments') }}
                        </x-nav-link>
                    @endcan

                    @can('shifts.view_own')
                        <x-nav-link href="{{ route('admin.shifts.index') }}" :active="request()->routeIs('admin.shifts.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.shifts') }}
                        </x-nav-link>
                    @endcan
                </x-nav-group>

                <x-nav-group :label="__('nav.admin.groups.menu')">
                    @can('viewAny', \App\Models\Category::class)
                        <x-nav-link href="{{ route('admin.categories.index') }}" :active="request()->routeIs('admin.categories.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.categories') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', \App\Models\Product::class)
                        <x-nav-link href="{{ route('admin.products.index') }}" :active="request()->routeIs('admin.products.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5 12 2.25 3 7.5m18 0-9 5.25M21 7.5v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.products') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', \App\Models\TableSection::class)
                        <x-nav-link href="{{ route('admin.table-sections.index') }}" :active="request()->routeIs('admin.table-sections.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.table_sections') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', \App\Models\Table::class)
                        <x-nav-link href="{{ route('admin.tables.index') }}" :active="request()->routeIs('admin.tables.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 4.5h17.25c.621 0 1.125.504 1.125 1.125v12.75c0 .621-.504 1.125-1.125 1.125H3.375A1.125 1.125 0 0 1 2.25 18.375V5.625c0-.621.504-1.125 1.125-1.125ZM9 4.5v14.25M15 4.5v14.25M2.25 9.75h19.5M2.25 14.25h19.5" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.tables') }}
                        </x-nav-link>
                    @endcan

                    @can('qr.manage')
                        <x-nav-link href="{{ route('admin.qr.menu') }}" :active="request()->routeIs('admin.qr.menu*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5A.75.75 0 0 1 4.5 3.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-.75.75h-4.5a.75.75 0 0 1-.75-.75v-4.5ZM3.75 15A.75.75 0 0 1 4.5 14.25h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-.75.75h-4.5a.75.75 0 0 1-.75-.75V15ZM14.25 4.5a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-.75.75h-4.5a.75.75 0 0 1-.75-.75v-4.5Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75zM6.75 17.25h.75V18h-.75zM17.25 6.75H18v.75h-.75zM14.25 14.25h1.5v1.5h-1.5zM19.5 19.5h-1.5v-1.5M14.25 19.5v-2.25M19.5 14.25h-2.25" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.qr_menu') }}
                        </x-nav-link>
                    @endcan
                </x-nav-group>

                <x-nav-group :label="__('nav.admin.groups.customers')">
                    @can('customers.view')
                        <x-nav-link href="{{ route('admin.customers.index') }}" :active="request()->routeIs('admin.customers.*') || request()->routeIs('admin.customer-groups.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.customers') }}
                        </x-nav-link>
                    @endcan

                    @can('discounts.view')
                        <x-nav-link href="{{ route('admin.discounts.index') }}" :active="request()->routeIs('admin.discounts.*') || request()->routeIs('admin.campaigns.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.discounts') }}
                        </x-nav-link>
                    @endcan

                    @can('loyalty.view')
                        <x-nav-link href="{{ route('admin.loyalty-rules.index') }}" :active="request()->routeIs('admin.loyalty-rules.*') || request()->routeIs('admin.loyalty-rewards.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 21.03a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.loyalty') }}
                        </x-nav-link>
                    @endcan
                </x-nav-group>

                <x-nav-group :label="__('nav.admin.groups.inventory')">
                    @can('inventory.view')
                        <x-nav-link href="{{ route('admin.inventory.items.index') }}" :active="request()->routeIs('admin.inventory.*') || request()->routeIs('admin.suppliers.*') || request()->routeIs('admin.purchases.*') || request()->routeIs('admin.stock-adjustments.*') || request()->routeIs('admin.waste-records.*') || request()->routeIs('admin.purchase-returns.*') || request()->routeIs('admin.stock-counts.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5V18a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18V7.5m18 0A2.25 2.25 0 0 0 18.75 5.25H5.25A2.25 2.25 0 0 0 3 7.5m18 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.07 9.66A2.25 2.25 0 0 1 3 7.743V7.5" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.inventory') }}
                        </x-nav-link>
                    @endcan
                </x-nav-group>

                <x-nav-group :label="__('nav.admin.groups.insights')">
                    @can('reports.view')
                        <x-nav-link href="{{ route('admin.reports.index') }}" :active="request()->routeIs('admin.reports.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.reports') }}
                        </x-nav-link>
                    @endcan

                    @can('audit.view')
                        <x-nav-link href="{{ route('admin.audit-logs.index') }}" :active="request()->routeIs('admin.audit-logs.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.audit_logs') }}
                        </x-nav-link>
                    @endcan
                </x-nav-group>

                <x-nav-group :label="__('nav.admin.groups.settings')">
                    @can('viewAny', \App\Models\User::class)
                        <x-nav-link href="{{ route('admin.users.index') }}" :active="request()->routeIs('admin.users.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.users') }}
                        </x-nav-link>
                    @endcan

                    @can('settings.manage')
                        <x-nav-link href="{{ route('admin.settings.business.edit') }}" :active="request()->routeIs('admin.settings.business.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.business_settings') }}
                        </x-nav-link>
                        <x-nav-link href="{{ route('admin.settings.system.edit') }}" :active="request()->routeIs('admin.settings.system.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.751.43.991l1.005.828c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.751-.43-.991l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.system_settings') }}
                        </x-nav-link>
                    @endcan

                    @can('invoices.manage')
                        <x-nav-link href="{{ route('admin.invoice-templates.index') }}" :active="request()->routeIs('admin.invoice-templates.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 3h6m-7.5 6h9a2.25 2.25 0 0 0 2.25-2.25V6.31a2.25 2.25 0 0 0-.659-1.591l-2.909-2.909A2.25 2.25 0 0 0 13.5 1.5H6.75A2.25 2.25 0 0 0 4.5 3.75v15A2.25 2.25 0 0 0 6.75 21Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.invoice_templates') }}
                        </x-nav-link>
                    @endcan

                    @can('printers.manage')
                        <x-nav-link href="{{ route('admin.printers.index') }}" :active="request()->routeIs('admin.printers.*') || request()->routeIs('admin.print-routes.*')">
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('nav.admin.printers') }}
                        </x-nav-link>
                    @endcan
                </x-nav-group>

            </nav>

            <form method="POST" action="{{ route('logout') }}" class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-start text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5 text-gray-400 dark:text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                    </svg>
                    {{ __('nav.admin.logout') }}
                </button>
            </form>
        </aside>

        <main class="min-w-0 flex-1 bg-gray-50 dark:bg-gray-950 p-4 sm:p-6">
            @vite('resources/js/admin-notifications.js')
            <script>
                window.ADMIN_USER_ID = {{ auth()->id() }};
                window.ADMIN_WAITER_CALLS_URL = @json(auth()->user()->can('waiter_calls.view') ? route('admin.waiter-calls.index') : null);
            </script>

            <div class="mb-4 flex items-center justify-between gap-2">
                <button id="admin-sidebar-open" type="button" aria-label="{{ __('nav.admin.open_menu') }}" aria-controls="admin-sidebar" @class(['rounded-lg bg-white dark:bg-gray-900 p-2 text-gray-600 dark:text-gray-400 shadow-sm', 'md:hidden' => ! $focusMode, '2xl:hidden' => $focusMode])>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    </svg>
                </button>

                <div class="flex items-center gap-2">
                <x-theme-toggle />
                <x-locale-switcher />

                <x-dropdown align="end">
                    <x-slot:trigger>
                        <span class="inline-flex items-center gap-1 rounded-lg bg-white dark:bg-gray-900 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                            {{ __('admin.notifications.title') }}
                            <span data-notification-count {{ auth()->user()->unreadNotifications->isEmpty() ? 'hidden' : '' }} class="ms-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs text-white">
                                {{ auth()->user()->unreadNotifications->count() }}
                            </span>
                        </span>
                    </x-slot:trigger>

                    <div class="w-80">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-3 py-2">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('admin.notifications.recent') }}</span>
                            <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">{{ __('admin.notifications.mark_all_read') }}</button>
                            </form>
                        </div>
                        <div class="max-h-80 divide-y divide-gray-100 dark:divide-gray-800 overflow-y-auto">
                            @forelse (auth()->user()->notifications()->latest()->limit(10)->get() as $notification)
                                <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}" class="block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="block w-full px-3 py-2 text-start text-sm {{ $notification->read_at ? 'text-gray-500 dark:text-gray-400' : 'font-medium text-gray-900 dark:text-gray-100' }} hover:bg-gray-50 dark:hover:bg-gray-800">
                                        {{ $notification->data['message'] ?? __('admin.notifications.title') }}
                                        <span class="block text-xs text-gray-400 dark:text-gray-500">{{ \App\Support\LocalizedDate::diffForHumans($notification->created_at) }}</span>
                                    </button>
                                </form>
                            @empty
                                <p class="p-3 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.notifications.empty') }}</p>
                            @endforelse
                        </div>
                    </div>
                </x-dropdown>
                </div>
            </div>

            @if (session('status'))
                <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
            @endif

            @if (session('error'))
                <x-alert type="danger" class="mb-4">{{ session('error') }}</x-alert>
            @endif

            @yield('admin-content')
        </main>
    </div>
@endsection
