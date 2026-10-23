@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.tables.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.tables.store') }}" class="space-y-4">
            @csrf

            <x-select name="table_section_id" :label="__('admin.fields.section')" required>
                <option value="">{{ __('admin.tables.select_section') }}</option>
                @foreach ($sections as $section)
                    <option value="{{ $section->id }}" @selected(old('table_section_id') == $section->id)>{{ $section->name }}</option>
                @endforeach
            </x-select>

            <x-input name="name" :label="__('admin.tables.name_number')" value="{{ old('name') }}" required />

            <x-input name="capacity" type="number" :label="__('admin.fields.capacity')" value="{{ old('capacity') }}" min="1" />

            <x-select name="status" :label="__('admin.fields.status')">
                <option value="available" @selected(old('status', 'available') === 'available')>{{ __('admin.tables.status_available') }}</option>
                <option value="out_of_service" @selected(old('status') === 'out_of_service')>{{ __('admin.tables.status_out_of_service') }}</option>
            </x-select>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
