@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.inventory.units.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.inventory.units.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-input name="abbreviation" :label="__('admin.inventory.units.abbreviation')" value="{{ old('abbreviation') }}" required />

            <x-select name="type" :label="__('admin.inventory.units.type')" required>
                <option value="">{{ __('admin.inventory.units.select_type') }}</option>
                @foreach (['mass', 'volume', 'count'] as $type)
                    <option value="{{ $type }}" @selected(old('type') === $type)>{{ __('admin.inventory.units.type_'.$type) }}</option>
                @endforeach
            </x-select>

            <x-input name="base_factor" type="number" step="0.000001" min="0" :label="__('admin.inventory.units.base_factor')" value="{{ old('base_factor', 1) }}" :hint="__('admin.inventory.units.base_factor_hint')" required />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
