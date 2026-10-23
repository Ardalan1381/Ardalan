@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.categories.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-select name="parent_id" :label="__('admin.categories.parent_category')">
                <option value="">{{ __('admin.categories.no_parent') }}</option>
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}" @selected(old('parent_id') == $option->id)>{{ $option->name }}</option>
                @endforeach
            </x-select>

            <x-textarea name="description" :label="__('common.labels.description')" rows="3">{{ old('description') }}</x-textarea>

            <x-input name="sort_order" type="number" :label="__('admin.fields.sort_order')" value="{{ old('sort_order', 0) }}" min="0" />

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
