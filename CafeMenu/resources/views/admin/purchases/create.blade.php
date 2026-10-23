@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.purchases.new') }}</h1>

    <x-card class="mt-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.purchases.store') }}" class="space-y-4">
            @csrf

            <x-select name="supplier_id" :label="__('admin.suppliers.title')" required>
                <option value="">{{ __('admin.purchases.select_supplier') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </x-select>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="purchase_date" type="date" :label="__('admin.purchases.purchase_date')" value="{{ old('purchase_date', now()->toDateString()) }}" required />
                <x-input name="expected_date" type="date" :label="__('admin.purchases.expected_date')" value="{{ old('expected_date') }}" />
            </div>

            <x-textarea name="notes" :label="__('common.labels.description')" rows="2">{{ old('notes') }}</x-textarea>

            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.purchases.lines_title') }}</p>

                <div data-repeater data-repeater-next-index="1" class="mt-2">
                    <div data-repeater-rows class="space-y-2">
                        <div data-repeater-row class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                            <select name="items[0][inventory_item_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <option value="">{{ __('admin.inventory.select_item') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                            <select name="items[0][unit_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <option value="">{{ __('admin.fields.unit') }}</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->abbreviation }}</option>
                                @endforeach
                            </select>
                            <input type="text" inputmode="decimal" name="items[0][quantity]" placeholder="{{ __('admin.inventory.quantity') }}" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" name="items[0][unit_cost]" placeholder="{{ __('admin.purchases.unit_cost') }}" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <button type="button" data-repeater-remove class="shrink-0 text-xs text-danger hover:underline">{{ __('admin.inventory.remove_line') }}</button>
                            </div>
                        </div>
                    </div>

                    <template data-repeater-template>
                        <div data-repeater-row class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                            <select name="items[__INDEX__][inventory_item_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <option value="">{{ __('admin.inventory.select_item') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                            <select name="items[__INDEX__][unit_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <option value="">{{ __('admin.fields.unit') }}</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->abbreviation }}</option>
                                @endforeach
                            </select>
                            <input type="text" inputmode="decimal" name="items[__INDEX__][quantity]" placeholder="{{ __('admin.inventory.quantity') }}" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" name="items[__INDEX__][unit_cost]" placeholder="{{ __('admin.purchases.unit_cost') }}" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <button type="button" data-repeater-remove class="shrink-0 text-xs text-danger hover:underline">{{ __('admin.inventory.remove_line') }}</button>
                            </div>
                        </div>
                    </template>

                    <button type="button" data-repeater-add class="mt-2 text-xs text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.purchases.add_line') }}</button>
                </div>
            </div>

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
