@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.waste.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.waste-records.store') }}" class="space-y-4">
            @csrf

            <x-select name="inventory_item_id" :label="__('admin.fields.name')" required>
                <option value="">{{ __('admin.inventory.select_item') }}</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}" @selected(old('inventory_item_id') == $item->id)>{{ $item->name }} ({{ $item->baseUnit->abbreviation }})</option>
                @endforeach
            </x-select>

            <x-input name="quantity" type="text" inputmode="decimal" :label="__('admin.inventory.quantity')" value="{{ old('quantity') }}" required />

            <x-select name="reason" :label="__('admin.waste.reason')" required>
                <option value="">{{ __('admin.waste.select_reason') }}</option>
                @foreach (['spoilage', 'breakage', 'expired', 'preparation_error', 'other'] as $reason)
                    <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ __('admin.waste.reason_'.$reason) }}</option>
                @endforeach
            </x-select>

            <x-input name="wasted_at" type="datetime-local" :label="__('admin.waste.wasted_at')" value="{{ old('wasted_at', now()->format('Y-m-d\TH:i')) }}" />

            <x-textarea name="notes" :label="__('common.labels.description')" rows="2">{{ old('notes') }}</x-textarea>

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
