@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.loyalty_rules.new') }}</h1>

    <x-card class="mt-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.loyalty-rules.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-select name="type" :label="__('admin.fields.type')" required>
                <option value="">{{ __('admin.loyalty_rules.select_type') }}</option>
                @foreach (['spend_threshold', 'buy_x_get_y_points'] as $type)
                    <option value="{{ $type }}" @selected(old('type') === $type)>{{ __('admin.loyalty_rules.type_labels.'.$type) }}</option>
                @endforeach
            </x-select>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="min_spend_amount" type="number" min="0" :label="__('admin.loyalty_rules.min_spend_amount')" value="{{ old('min_spend_amount') }}" :hint="__('admin.loyalty_rules.min_spend_amount_hint')" />
                <x-input name="points_earned" type="number" min="0" :label="__('admin.loyalty_rules.points_earned')" value="{{ old('points_earned') }}" :hint="__('admin.loyalty_rules.points_earned_hint')" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="buy_quantity" type="number" min="1" :label="__('admin.loyalty_rules.buy_quantity')" value="{{ old('buy_quantity') }}" :hint="__('admin.loyalty_rules.buy_quantity_hint')" />
                <x-input name="get_bonus_points" type="number" min="0" :label="__('admin.loyalty_rules.get_bonus_points')" value="{{ old('get_bonus_points') }}" :hint="__('admin.loyalty_rules.get_bonus_points_hint')" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="starts_at" type="date" :label="__('admin.loyalty_rules.starts_at')" value="{{ old('starts_at') }}" />
                <x-input name="ends_at" type="date" :label="__('admin.loyalty_rules.ends_at')" value="{{ old('ends_at') }}" />
            </div>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
