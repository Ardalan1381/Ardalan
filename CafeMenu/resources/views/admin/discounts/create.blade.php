@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.discounts.new') }}</h1>

    <x-card class="mt-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.discounts.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-textarea name="description" :label="__('common.labels.description')" rows="2">{{ old('description') }}</x-textarea>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select name="type" :label="__('admin.fields.type')" required>
                    <option value="">{{ __('admin.discounts.select_type') }}</option>
                    @foreach (['percentage', 'fixed_amount', 'item_discount', 'half_price', 'buy_x_get_y', 'free_item'] as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ __('admin.discounts.type_labels.'.$type) }}</option>
                    @endforeach
                </x-select>

                <x-input name="value" type="number" :label="__('admin.fields.value')" value="{{ old('value') }}" :hint="__('admin.discounts.value_hint')" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="buy_quantity" type="number" min="1" :label="__('admin.discounts.buy_quantity')" value="{{ old('buy_quantity') }}" />
                <x-input name="get_quantity" type="number" min="1" :label="__('admin.discounts.get_quantity')" value="{{ old('get_quantity') }}" />
            </div>

            <x-select name="free_item_product_id" :label="__('admin.discounts.free_item_product')">
                <option value="">{{ __('admin.discounts.select_product') }}</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(old('free_item_product_id') == $product->id)>{{ $product->name }}</option>
                @endforeach
            </x-select>

            <x-select name="applies_to" :label="__('admin.discounts.applies_to')" required>
                @foreach (['order', 'specific_products', 'specific_categories'] as $appliesTo)
                    <option value="{{ $appliesTo }}" @selected(old('applies_to', 'order') === $appliesTo)>{{ __('admin.discounts.applies_to_labels.'.$appliesTo) }}</option>
                @endforeach
            </x-select>

            <x-select id="product_ids" name="product_ids[]" multiple size="5" :label="__('admin.discounts.products')">
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(in_array($product->id, old('product_ids', [])))>{{ $product->name }}</option>
                @endforeach
            </x-select>

            <x-select id="category_ids" name="category_ids[]" multiple size="5" :label="__('admin.discounts.categories')">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(in_array($category->id, old('category_ids', [])))>{{ $category->name }}</option>
                @endforeach
            </x-select>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="priority" type="number" :label="__('admin.fields.priority')" value="{{ old('priority', 0) }}" />
                <x-select name="campaign_id" :label="__('admin.discounts.campaign')">
                    <option value="">{{ __('admin.discounts.no_campaign') }}</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected(old('campaign_id') == $campaign->id)>{{ $campaign->name }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="flex flex-wrap gap-6">
                <x-checkbox name="is_stackable" :label="__('admin.discounts.is_stackable')" value="1" :checked="old('is_stackable')" />
                <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="starts_at" type="date" :label="__('admin.discounts.starts_at')" value="{{ old('starts_at') }}" />
                <x-input name="ends_at" type="date" :label="__('admin.discounts.ends_at')" value="{{ old('ends_at') }}" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="start_time" type="time" :label="__('admin.discounts.start_time')" value="{{ old('start_time') ? substr(old('start_time'), 0, 5) : '' }}" />
                <x-input name="end_time" type="time" :label="__('admin.discounts.end_time')" value="{{ old('end_time') ? substr(old('end_time'), 0, 5) : '' }}" />
            </div>

            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.discounts.days_of_week') }}</p>
                <div class="mt-1 flex flex-wrap gap-4">
                    @foreach (__('admin.discounts.days') as $day => $label)
                        <x-checkbox name="days_of_week[{{ $day }}]" :label="$label" value="1" :checked="in_array($day, old('days_of_week', []))" />
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.discounts.days_of_week_hint') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="min_order_amount" type="number" min="0" :label="__('admin.discounts.min_order_amount')" value="{{ old('min_order_amount') }}" />
                <x-input name="min_quantity" type="number" min="1" :label="__('admin.discounts.min_quantity')" value="{{ old('min_quantity') }}" />
            </div>

            <x-select id="customer_ids" name="customer_ids[]" multiple size="5" :label="__('admin.discounts.eligible_customers')" :hint="__('admin.discounts.eligibility_hint')">
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(in_array($customer->id, old('customer_ids', [])))>{{ $customer->name }}</option>
                @endforeach
            </x-select>

            <x-select id="customer_group_ids" name="customer_group_ids[]" multiple size="5" :label="__('admin.discounts.eligible_customer_groups')">
                @foreach ($customerGroups as $group)
                    <option value="{{ $group->id }}" @selected(in_array($group->id, old('customer_group_ids', [])))>{{ $group->name }}</option>
                @endforeach
            </x-select>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="usage_limit" type="number" min="1" :label="__('admin.discounts.usage_limit')" value="{{ old('usage_limit') }}" />
                <x-input name="usage_limit_per_customer" type="number" min="1" :label="__('admin.discounts.usage_limit_per_customer')" value="{{ old('usage_limit_per_customer') }}" />
            </div>

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
