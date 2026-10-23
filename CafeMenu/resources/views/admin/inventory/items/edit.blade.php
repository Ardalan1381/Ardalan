@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $item->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.inventory.items.update', $item) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $item->name) }}" required />

            <x-input name="sku" :label="__('admin.fields.sku')" value="{{ old('sku', $item->sku) }}" />

            <x-select name="inventory_category_id" :label="__('admin.fields.category')">
                <option value="">{{ __('admin.inventory.select_category') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('inventory_category_id', $item->inventory_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select>

            <x-select name="base_unit_id" :label="__('admin.fields.unit')" required>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected(old('base_unit_id', $item->base_unit_id) == $unit->id)>{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                @endforeach
            </x-select>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="min_stock_level" type="number" step="0.0001" min="0" :label="__('admin.inventory.min_stock_level')" value="{{ old('min_stock_level', $item->min_stock_level) }}" />
                <x-input name="max_stock_level" type="number" step="0.0001" min="0" :label="__('admin.inventory.max_stock_level')" value="{{ old('max_stock_level', $item->max_stock_level) }}" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <p class="text-gray-500 dark:text-gray-400">{{ __('admin.inventory.current_stock') }}</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->current_stock }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">{{ __('admin.inventory.average_cost') }}</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->average_cost !== null ? \App\Support\Money::format($item->average_cost) : '—' }}</p>
                </div>
            </div>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $item->is_active)" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
