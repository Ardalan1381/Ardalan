@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $category->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.inventory.categories.update', $category) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $category->name) }}" required />

            <x-select name="parent_id" :label="__('admin.inventory.categories.parent_category')">
                <option value="">{{ __('admin.inventory.categories.no_parent') }}</option>
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}" @selected(old('parent_id', $category->parent_id) == $option->id)>{{ $option->name }}</option>
                @endforeach
            </x-select>

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
