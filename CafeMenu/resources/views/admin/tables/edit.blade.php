@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $table->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.tables.update', $table) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-select name="table_section_id" :label="__('admin.fields.section')" required>
                @foreach ($sections as $section)
                    <option value="{{ $section->id }}" @selected(old('table_section_id', $table->table_section_id) == $section->id)>{{ $section->name }}</option>
                @endforeach
            </x-select>

            <x-input name="name" :label="__('admin.tables.name_number')" value="{{ old('name', $table->name) }}" required />

            <x-input name="capacity" type="number" :label="__('admin.fields.capacity')" value="{{ old('capacity', $table->capacity) }}" min="1" />

            <x-select name="status" :label="__('admin.fields.status')">
                <option value="available" @selected(old('status', $table->status) === 'available')>{{ __('admin.tables.status_available') }}</option>
                <option value="out_of_service" @selected(old('status', $table->status) === 'out_of_service')>{{ __('admin.tables.status_out_of_service') }}</option>
            </x-select>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $table->is_active)" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>

    @can('view', $table)
        <p class="mt-4 text-sm">
            <a href="{{ route('admin.tables.qr', $table) }}" class="text-primary hover:underline">{{ __('admin.tables.view_qr_code') }}</a>
        </p>
    @endcan
@endsection
