@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.stock_counts.new') }}</h1>

    <x-card class="mt-6 max-w-lg">
        <form method="POST" action="{{ route('admin.stock-counts.store') }}" class="space-y-4">
            @csrf

            <x-checkbox name="select_all_active" id="select_all_active" :label="__('admin.stock_counts.select_all_active')" value="1" />

            <x-select id="inventory_item_ids" name="inventory_item_ids[]" multiple size="10" :label="__('admin.stock_counts.select_items')">
                @foreach ($items as $item)
                    <option value="{{ $item->id }}" @selected(in_array($item->id, old('inventory_item_ids', [])))>{{ $item->name }}</option>
                @endforeach
            </x-select>

            <x-textarea name="notes" :label="__('common.labels.description')" rows="2">{{ old('notes') }}</x-textarea>

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>

    <script>
        // The "select all active" checkbox is a shortcut the form request
        // expands server-side (see StartStockCountRequest) — disabling the
        // multi-select while it's checked just makes that relationship
        // visible so a stray leftover selection doesn't look ignored.
        document.getElementById('select_all_active')?.addEventListener('change', (event) => {
            document.getElementById('inventory_item_ids').disabled = event.target.checked;
        });
    </script>
@endsection
