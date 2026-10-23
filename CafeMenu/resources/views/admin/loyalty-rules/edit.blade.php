@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $loyaltyRule->name }}</h1>

    <x-card class="mt-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.loyalty-rules.update', $loyaltyRule) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $loyaltyRule->name) }}" required />

            <x-select name="type" :label="__('admin.fields.type')" required>
                <option value="">{{ __('admin.loyalty_rules.select_type') }}</option>
                @foreach (['spend_threshold', 'buy_x_get_y_points'] as $type)
                    <option value="{{ $type }}" @selected(old('type', $loyaltyRule->type) === $type)>{{ __('admin.loyalty_rules.type_labels.'.$type) }}</option>
                @endforeach
            </x-select>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="min_spend_amount" type="number" min="0" :label="__('admin.loyalty_rules.min_spend_amount')" value="{{ old('min_spend_amount', $loyaltyRule->min_spend_amount) }}" :hint="__('admin.loyalty_rules.min_spend_amount_hint')" />
                <x-input name="points_earned" type="number" min="0" :label="__('admin.loyalty_rules.points_earned')" value="{{ old('points_earned', $loyaltyRule->points_earned) }}" :hint="__('admin.loyalty_rules.points_earned_hint')" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="buy_quantity" type="number" min="1" :label="__('admin.loyalty_rules.buy_quantity')" value="{{ old('buy_quantity', $loyaltyRule->buy_quantity) }}" :hint="__('admin.loyalty_rules.buy_quantity_hint')" />
                <x-input name="get_bonus_points" type="number" min="0" :label="__('admin.loyalty_rules.get_bonus_points')" value="{{ old('get_bonus_points', $loyaltyRule->get_bonus_points) }}" :hint="__('admin.loyalty_rules.get_bonus_points_hint')" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="starts_at" type="date" :label="__('admin.loyalty_rules.starts_at')" value="{{ old('starts_at', $loyaltyRule->starts_at?->toDateString()) }}" />
                <x-input name="ends_at" type="date" :label="__('admin.loyalty_rules.ends_at')" value="{{ old('ends_at', $loyaltyRule->ends_at?->toDateString()) }}" />
            </div>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $loyaltyRule->is_active)" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
