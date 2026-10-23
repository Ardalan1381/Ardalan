@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.loyalty_rewards.new') }}</h1>

    <x-card class="mt-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.loyalty-rewards.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="points_cost" type="number" min="1" :label="__('admin.loyalty_rewards.points_cost')" value="{{ old('points_cost') }}" required />

                <x-select name="reward_type" :label="__('admin.loyalty_rewards.reward_type')" required>
                    <option value="">{{ __('admin.loyalty_rewards.select_type') }}</option>
                    @foreach (['fixed_discount_amount', 'percentage_discount', 'free_product'] as $type)
                        <option value="{{ $type }}" @selected(old('reward_type') === $type)>{{ __('admin.loyalty_rewards.reward_type_labels.'.$type) }}</option>
                    @endforeach
                </x-select>
            </div>

            <x-input name="value" type="number" min="0" :label="__('admin.fields.value')" value="{{ old('value') }}" :hint="__('admin.loyalty_rewards.value_hint')" />

            <x-select name="free_product_id" :label="__('admin.loyalty_rewards.free_product')">
                <option value="">{{ __('admin.loyalty_rewards.select_product') }}</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(old('free_product_id') == $product->id)>{{ $product->name }}</option>
                @endforeach
            </x-select>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
