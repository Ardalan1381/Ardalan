@extends('layouts.admin')

@section('focus-mode', '1')

@section('admin-content')
    @vite('resources/js/pos.js')

    <a href="{{ route('admin.pos.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">{{ __('admin.pos.back_to_tables') }}</a>

    <div class="mt-2">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            {{ __('admin.pos.table_heading', ['table' => $table->name, 'section' => $table->section->name]) }}
        </h1>
        @if ($table->status === 'out_of_service')
            <p class="mt-1 text-sm font-medium text-danger">{{ __('admin.pos.table_out_of_service_notice') }}</p>
        @endif
    </div>

    @if ($errors->any())
        <x-alert type="danger" class="mt-4">{{ $errors->first() }}</x-alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($existingOrder)
                <x-card :title="__('admin.pos.existing_order_title')">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $existingOrder->order_number }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('orders.type_labels.'.$existingOrder->type) }}</p>
                        </div>
                        <x-badge :status="$existingOrder->status">{{ __('orders.status.'.$existingOrder->status) }}</x-badge>
                    </div>

                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.pos.existing_order_notice') }}</p>

                    <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800 border-t border-gray-100 dark:border-gray-800">
                        @forelse ($existingOrder->items as $item)
                            <div class="flex items-start justify-between gap-3 py-2 text-sm">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->quantity }}&times; {{ $item->product_name_snapshot }}</p>
                                    @if ($item->options->isNotEmpty())
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->options->pluck('option_name_snapshot')->join(', ') }}</p>
                                    @endif
                                    @if ($item->item_notes)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('orders.note_prefix', ['note' => $item->item_notes]) }}</p>
                                    @endif
                                </div>
                                <p class="shrink-0 text-sm font-medium text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($item->line_total) }}</p>
                            </div>
                        @empty
                            <p class="py-3 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.pos.existing_order_empty') }}</p>
                        @endforelse
                    </div>

                    <a href="{{ route('admin.orders.show', $existingOrder) }}" class="mt-3 inline-flex items-center text-sm font-medium text-primary hover:underline">
                        {{ __('common.actions.view') }} &rarr;
                    </a>
                </x-card>
            @endif

            <x-card :title="__('admin.pos.catalog_title')">
                @include('admin.pos._catalog', ['categories' => $categories])
            </x-card>
        </div>

        <div class="lg:col-span-1">
            <form
                id="pos-order-form"
                data-pos-form
                method="POST"
                action="{{ $existingOrder ? route('admin.pos.orders.items.store', $existingOrder) : route('admin.pos.table.orders.store', $table) }}"
            >
                @csrf
                <x-card :title="$existingOrder ? __('admin.pos.add_items_submit') : __('admin.pos.new_order_title')">
                    @unless ($existingOrder)
                        <div class="space-y-4">
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
                    @endunless

                    <div class="{{ $existingOrder ? '' : 'mt-5 border-t border-gray-100 dark:border-gray-800 pt-4' }}">
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
                            {{ $existingOrder ? __('admin.pos.add_items_submit') : __('admin.pos.new_order_submit') }}
                        </x-button>
                    </div>
                </x-card>
            </form>
        </div>
    </div>
@endsection
