@extends('layouts.app')

@section('title', __('cart.title').' - '.$business->name)

@section('content')
    @vite('resources/js/cart.js')

    <div
        class="min-h-screen bg-gray-50 dark:bg-gray-950 pb-8"
        id="cart-page"
        data-accepts-dine-in="{{ $business->accepts_dine_in ? '1' : '0' }}"
        data-accepts-takeaway="{{ $business->accepts_takeaway ? '1' : '0' }}"
        data-accepts-pickup="{{ $business->accepts_pickup ? '1' : '0' }}"
        data-accepts-delivery="{{ $business->accepts_delivery ? '1' : '0' }}"
        data-accepts-cash-on-pickup="{{ $business->accepts_cash_on_pickup ? '1' : '0' }}"
        data-has-table="{{ $table ? '1' : '0' }}"
    >
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
            </div>
        </div>

        <div class="mx-auto max-w-2xl px-4 pt-5">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('cart.title') }}</h1>

            @if ($business->ordering_enabled)
                <div id="cart-items" class="mt-4 space-y-2"></div>
                <x-empty-state id="cart-empty" hidden :title="__('cart.empty')" class="mt-4 border-0 bg-white dark:bg-gray-900 shadow-sm" />

                <div class="mt-4 flex items-center justify-between rounded-xl bg-white dark:bg-gray-900 px-4 py-3 text-sm font-semibold shadow-sm">
                    <span>{{ __('cart.subtotal') }}</span>
                    <span id="cart-subtotal" class="text-primary"></span>
                </div>

                <div class="mt-4 space-y-4 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <x-select id="order-type" name="order-type" :label="__('cart.order_type')">
                        <option value="dine_in">{{ __('cart.order_types.dine_in') }}</option>
                        <option value="takeaway">{{ __('cart.order_types.takeaway') }}</option>
                        <option value="pickup">{{ __('cart.order_types.pickup') }}</option>
                        <option value="delivery">{{ __('cart.order_types.delivery') }}</option>
                    </x-select>

                    <div id="customer-fields" class="space-y-4">
                        <x-input id="customer-name" name="customer-name" :label="__('cart.customer_name')" />
                        <x-input id="customer-phone" name="customer-phone" :label="__('cart.customer_phone')" />
                    </div>

                    <div id="delivery-address-field" hidden>
                        <x-textarea id="delivery-address" name="delivery-address" :label="__('cart.delivery_address')" rows="2" />
                    </div>

                    <div id="cash-on-pickup-field" hidden>
                        <x-checkbox id="pay-cash-on-pickup" name="pay-cash-on-pickup" :label="__('cart.pay_cash_on_pickup')" />
                    </div>

                    <x-textarea id="order-notes" name="order-notes" :label="__('cart.order_notes')" rows="2" />

                    <p id="checkout-error" hidden class="rounded-lg bg-danger-subtle px-3 py-2 text-sm text-danger"></p>

                    <button id="place-order" class="w-full rounded-xl bg-primary px-4 py-3.5 text-sm font-semibold text-white shadow-md transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('cart.place_order') }}
                    </button>
                </div>
            @else
                <x-alert type="warning" class="mt-4">{{ __('menu.ordering_disabled_banner') }}</x-alert>
            @endif
        </div>
    </div>
@endsection
