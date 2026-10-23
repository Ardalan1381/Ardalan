@props(['recipable', 'action', 'items', 'units'])

@php
    $lines = $recipable->recipeItems;
@endphp

<form method="POST" action="{{ $action }}" class="space-y-3">
    @csrf
    @method('PUT')

    <div data-repeater data-repeater-next-index="{{ $lines->count() }}">
        <div data-repeater-rows class="space-y-2">
            @foreach ($lines as $index => $line)
                <div data-repeater-row class="flex flex-wrap items-end gap-2">
                    <select name="lines[{{ $index }}][inventory_item_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected($item->id === $line->inventory_item_id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" inputmode="decimal" name="lines[{{ $index }}][quantity]" value="{{ $line->quantity }}" placeholder="{{ __('admin.inventory.quantity') }}" class="w-24 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                    <select name="lines[{{ $index }}][unit_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" @selected($unit->id === $line->unit_id)>{{ $unit->abbreviation }}</option>
                        @endforeach
                    </select>
                    <button type="button" data-repeater-remove class="text-xs text-danger hover:underline">{{ __('admin.inventory.remove_line') }}</button>
                </div>
            @endforeach
        </div>

        @if ($lines->isEmpty())
            <p class="text-xs text-gray-500 dark:text-gray-400" data-repeater-empty-hint>{{ __('admin.inventory.empty_recipe') }}</p>
        @endif

        <template data-repeater-template>
            <div data-repeater-row class="flex flex-wrap items-end gap-2">
                <select name="lines[__INDEX__][inventory_item_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                    <option value="">{{ __('admin.inventory.select_item') }}</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
                <input type="text" inputmode="decimal" name="lines[__INDEX__][quantity]" placeholder="{{ __('admin.inventory.quantity') }}" class="w-24 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                <select name="lines[__INDEX__][unit_id]" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm" required>
                    <option value="">{{ __('admin.inventory.select_unit') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->abbreviation }}</option>
                    @endforeach
                </select>
                <button type="button" data-repeater-remove class="text-xs text-danger hover:underline">{{ __('admin.inventory.remove_line') }}</button>
            </div>
        </template>

        <button type="button" data-repeater-add class="mt-2 text-xs text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.add_line') }}</button>
    </div>

    <div>
        <x-button type="submit" size="sm">{{ __('admin.inventory.save_recipe') }}</x-button>
    </div>
</form>
