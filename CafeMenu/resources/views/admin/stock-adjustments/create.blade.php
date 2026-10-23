@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.stock_adjustments.new') }}</h1>

    <x-card class="mt-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.stock-adjustments.store') }}" class="space-y-4">
            @csrf

            <x-select name="reason" :label="__('admin.stock_adjustments.reason')" required>
                <option value="">{{ __('admin.stock_adjustments.select_reason') }}</option>
                @foreach (['damage', 'theft', 'expiry', 'correction', 'other'] as $reason)
                    <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ __('admin.stock_adjustments.reason_'.$reason) }}</option>
                @endforeach
            </x-select>

            <x-textarea name="notes" :label="__('common.labels.description')" rows="2">{{ old('notes') }}</x-textarea>

            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.inventory.title') }}</p>

                <div data-repeater data-repeater-next-index="1" class="mt-2">
                    <div data-repeater-rows class="space-y-2">
                        <div data-repeater-row class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                            <select name="lines[0][inventory_item_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <option value="">{{ __('admin.inventory.select_item') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->baseUnit->abbreviation }})</option>
                                @endforeach
                            </select>
                            <select name="lines[0][sign]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm">
                                <option value="+">{{ __('admin.stock_adjustments.sign_increase') }}</option>
                                <option value="-">{{ __('admin.stock_adjustments.sign_decrease') }}</option>
                            </select>
                            <input type="text" inputmode="decimal" name="lines[0][quantity]" placeholder="{{ __('admin.inventory.quantity') }}" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" name="lines[0][unit_cost]" placeholder="{{ __('admin.purchases.unit_cost') }} ({{ __('common.labels.optional') }})" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm">
                                <button type="button" data-repeater-remove class="shrink-0 text-xs text-danger hover:underline">{{ __('admin.inventory.remove_line') }}</button>
                            </div>
                        </div>
                    </div>

                    <template data-repeater-template>
                        <div data-repeater-row class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                            <select name="lines[__INDEX__][inventory_item_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                                <option value="">{{ __('admin.inventory.select_item') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->baseUnit->abbreviation }})</option>
                                @endforeach
                            </select>
                            <select name="lines[__INDEX__][sign]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm">
                                <option value="+">{{ __('admin.stock_adjustments.sign_increase') }}</option>
                                <option value="-">{{ __('admin.stock_adjustments.sign_decrease') }}</option>
                            </select>
                            <input type="text" inputmode="decimal" name="lines[__INDEX__][quantity]" placeholder="{{ __('admin.inventory.quantity') }}" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" name="lines[__INDEX__][unit_cost]" placeholder="{{ __('admin.purchases.unit_cost') }} ({{ __('common.labels.optional') }})" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm">
                                <button type="button" data-repeater-remove class="shrink-0 text-xs text-danger hover:underline">{{ __('admin.inventory.remove_line') }}</button>
                            </div>
                        </div>
                    </template>

                    <button type="button" data-repeater-add class="mt-2 text-xs text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.stock_adjustments.add_line') }}</button>
                </div>
            </div>

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
