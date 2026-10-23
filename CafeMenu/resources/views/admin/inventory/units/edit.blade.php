@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $unit->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.inventory.units.update', $unit) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $unit->name) }}" required />

            <x-input name="abbreviation" :label="__('admin.inventory.units.abbreviation')" value="{{ old('abbreviation', $unit->abbreviation) }}" required />

            <x-select name="type" :label="__('admin.inventory.units.type')" required>
                @foreach (['mass', 'volume', 'count'] as $type)
                    <option value="{{ $type }}" @selected(old('type', $unit->type) === $type)>{{ __('admin.inventory.units.type_'.$type) }}</option>
                @endforeach
            </x-select>

            <x-input name="base_factor" type="number" step="0.000001" min="0" :label="__('admin.inventory.units.base_factor')" value="{{ old('base_factor', $unit->base_factor) }}" :hint="__('admin.inventory.units.base_factor_hint')" required />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
