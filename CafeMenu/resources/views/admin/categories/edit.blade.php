@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $category->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $category->name) }}" required />

            <x-select name="parent_id" :label="__('admin.categories.parent_category')">
                <option value="">{{ __('admin.categories.no_parent') }}</option>
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}" @selected(old('parent_id', $category->parent_id) == $option->id)>{{ $option->name }}</option>
                @endforeach
            </x-select>

            <x-textarea name="description" :label="__('common.labels.description')" rows="3">{{ old('description', $category->description) }}</x-textarea>

            <x-input name="sort_order" type="number" :label="__('admin.fields.sort_order')" value="{{ old('sort_order', $category->sort_order) }}" min="0" />

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $category->is_active)" />

            <x-translation-fields :model="$category" :fields="['name' => __('admin.fields.name'), 'description' => __('common.labels.description')]" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>

    <x-card class="mt-6 max-w-md" :title="__('admin.media.title')">
        <div class="flex flex-wrap gap-4">
            @foreach ($category->media as $media)
                <div class="w-32">
                    <img src="{{ $media->url() }}" alt="" class="h-32 w-32 rounded-lg border border-gray-200 dark:border-gray-700 object-cover">
                    <p class="mt-1 text-xs {{ $media->is_primary ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">
                        {{ $media->is_primary ? __('admin.media.primary') : '' }}
                    </p>
                    <div class="mt-1 flex gap-2 text-xs">
                        @unless ($media->is_primary)
                            <form method="POST" action="{{ route('admin.categories.media.primary', [$category, $media]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.media.make_primary') }}</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('admin.categories.media.destroy', [$category, $media]) }}" data-confirm="{{ __('admin.media.confirm_remove') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-danger hover:underline">{{ __('common.actions.remove') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.categories.media.store', $category) }}" enctype="multipart/form-data" class="mt-4">
            @csrf
            <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
            <x-button type="submit" size="sm" class="ms-2">{{ __('admin.media.upload') }}</x-button>
        </form>
    </x-card>
@endsection
