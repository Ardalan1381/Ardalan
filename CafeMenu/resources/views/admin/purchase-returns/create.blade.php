@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.purchases.returns.new') }}</h1>

    <x-card class="mt-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.purchase-returns.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select name="supplier_id" :label="__('admin.suppliers.title')" required>
                    <option value="">{{ __('admin.purchases.select_supplier') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </x-select>

                <x-select name="purchase_id" :label="__('admin.purchases.returns.select_purchase')">
                    <option value="">—</option>
                    @foreach ($purchases as $purchase)
                        <option value="{{ $purchase->id }}" @selected(old('purchase_id') == $purchase->id)>{{ $purchase->purchase_number }}</option>
                    @endforeach
                </x-select>
            </div>

            <x-textarea name="reason" :label="__('admin.purchases.returns.reason')" rows="2">{{ old('reason') }}</x-textarea>

            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.inventory.title') }}</p>

                <div data-repeater data-repeater-next-index="1" class="mt-2">
                    <div data-repeater-rows class="space-y-2">
                        <div data-repeater-row class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <select name="lines[0][inventory_item_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <option value="">{{ __('admin.inventory.select_item') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->baseUnit->abbreviation }})</option>
                                @endforeach
                            </select>
                            <input type="text" inputmode="decimal" name="lines[0][quantity]" placeholder="{{ __('admin.inventory.quantity') }}" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" name="lines[0][unit_cost]" placeholder="{{ __('admin.purchases.unit_cost') }}" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <button type="button" data-repeater-remove class="shrink-0 text-xs text-danger hover:underline">{{ __('admin.inventory.remove_line') }}</button>
                            </div>
                        </div>
                    </div>

                    <template data-repeater-template>
                        <div data-repeater-row class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <select name="lines[__INDEX__][inventory_item_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <option value="">{{ __('admin.inventory.select_item') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->baseUnit->abbreviation }})</option>
                                @endforeach
                            </select>
                            <input type="text" inputmode="decimal" name="lines[__INDEX__][quantity]" placeholder="{{ __('admin.inventory.quantity') }}" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" name="lines[__INDEX__][unit_cost]" placeholder="{{ __('admin.purchases.unit_cost') }}" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
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
