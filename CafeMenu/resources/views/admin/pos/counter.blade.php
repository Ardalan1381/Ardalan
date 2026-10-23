@extends('layouts.admin')

@section('focus-mode', '1')

@section('admin-content')
    @vite('resources/js/pos.js')

    <a href="{{ route('admin.pos.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">{{ __('admin.pos.back_to_tables') }}</a>

    <h1 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.pos.counter_title') }}</h1>

    @if ($errors->any())
        <x-alert type="danger" class="mt-4">{{ $errors->first() }}</x-alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card :title="__('admin.pos.catalog_title')">
                @include('admin.pos._catalog', ['categories' => $categories])
            </x-card>
        </div>

        <div class="lg:col-span-1">
            <form id="pos-order-form" data-pos-form method="POST" action="{{ route('admin.pos.counter.orders.store') }}">
                @csrf
                <x-card :title="__('admin.pos.counter_title')">
                    <div class="space-y-4">
                        <x-select id="pos-order-type" name="type" :label="__('admin.pos.order_type_label')" required>
                            <option value="takeaway" @selected(old('type', 'takeaway') === 'takeaway')>{{ __('orders.type_labels.takeaway') }}</option>
                            <option value="pickup" @selected(old('type') === 'pickup')>{{ __('orders.type_labels.pickup') }}</option>
                            <option value="delivery" @selected(old('type') === 'delivery')>{{ __('orders.type_labels.delivery') }}</option>
                        </x-select>

                        <div id="pos-delivery-field" hidden>
                            <x-textarea id="delivery_address" name="delivery_address" :label="__('cart.delivery_address')" rows="2">{{ old('delivery_address') }}</x-textarea>
                        </div>

                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.pos.customer_label') }}</label>
                            <input
                                type="text"
                                id="pos-customer-search"
                                placeholder="{{ __('admin.pos.search_customer_placeholder') }}"
                                class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm focus:border-primary focus:ring-primary/30"
                            >
                            <select id="customer_id" name="customer_id" class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm focus:border-primary focus:ring-primary/30">
                                <option value="">{{ __('admin.pos.guest_customer') }}</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" data-search="{{ strtolower($customer->name.' '.($customer->phone ?? '')) }}" @selected(old('customer_id') == $customer->id)>
                                        {{ $customer->name }}{{ $customer->phone ? ' ('.$customer->phone.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="pos-customer-name-fields" class="space-y-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.pos.new_customer_hint') }}</p>
                            <x-input id="customer_name" name="customer_name" :label="__('cart.customer_name')" value="{{ old('customer_name') }}" />
                            <x-input id="customer_phone" name="customer_phone" :label="__('cart.customer_phone')" value="{{ old('customer_phone') }}" />
                        </div>

                        <div id="pos-loyalty-field" hidden>
                            <label for="loyalty_reward_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.pos.loyalty_reward_label') }}</label>
                            <select id="loyalty_reward_id" name="loyalty_reward_id" disabled class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm focus:border-primary focus:ring-primary/30">
                                <option value="">{{ __('admin.pos.loyalty_reward_none') }}</option>
                                @foreach ($loyaltyRewards as $reward)
                                    <option value="{{ $reward->id }}" @selected(old('loyalty_reward_id') == $reward->id)>
                                        {{ $reward->name }} &mdash; {{ __('admin.pos.loyalty_points_suffix', ['points' => $reward->points_cost]) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <x-textarea name="notes" :label="__('orders.notes')" rows="2">{{ old('notes') }}</x-textarea>
                    </div>

                    <div class="mt-5 border-t border-gray-100 dark:border-gray-800 pt-4">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.pos.current_order_title') }}</h3>
                        <div id="pos-cart-items" class="mt-2 space-y-2"></div>
                        <p id="pos-cart-empty" class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.pos.current_order_empty') }}</p>

                        <div class="mt-3 flex items-center justify-between text-sm font-semibold text-gray-900 dark:text-gray-100">
                            <span>{{ __('admin.pos.estimated_total') }}</span>
                            <span id="pos-cart-total" class="text-primary">{{ \App\Support\Money::format(0) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.pos.estimated_total_hint') }}</p>

                        <p id="pos-cart-error" hidden class="mt-2 rounded-lg bg-danger-subtle px-3 py-2 text-sm text-danger"></p>

                        <div id="pos-items-inputs"></div>

                        <x-button type="submit" id="pos-submit-button" class="mt-4 w-full">
                            {{ __('admin.pos.counter_submit') }}
                        </x-button>
                    </div>
                </x-card>
            </form>
        </div>
    </div>
@endsection
