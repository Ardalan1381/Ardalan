@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $tableSection->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.table-sections.update', $tableSection) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $tableSection->name) }}" required />

            <x-textarea name="description" :label="__('common.labels.description')" rows="3">{{ old('description', $tableSection->description) }}</x-textarea>

            <x-input name="sort_order" type="number" :label="__('admin.fields.sort_order')" value="{{ old('sort_order', $tableSection->sort_order) }}" min="0" />

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $tableSection->is_active)" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
