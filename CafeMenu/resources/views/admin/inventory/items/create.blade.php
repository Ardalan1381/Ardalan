@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.inventory.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.inventory.items.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-input name="sku" :label="__('admin.fields.sku')" value="{{ old('sku') }}" />

            <x-select name="inventory_category_id" :label="__('admin.fields.category')">
                <option value="">{{ __('admin.inventory.select_category') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('inventory_category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select>

            <x-select name="base_unit_id" :label="__('admin.fields.unit')" required>
                <option value="">{{ __('admin.inventory.select_unit') }}</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected(old('base_unit_id') == $unit->id)>{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                @endforeach
            </x-select>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="min_stock_level" type="number" step="0.0001" min="0" :label="__('admin.inventory.min_stock_level')" value="{{ old('min_stock_level') }}" />
                <x-input name="max_stock_level" type="number" step="0.0001" min="0" :label="__('admin.inventory.max_stock_level')" value="{{ old('max_stock_level') }}" />
            </div>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
