@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.inventory.categories.title') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('admin.inventory.items.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.inventory') }}</a>
            @can('create', \App\Models\InventoryCategory::class)
                <x-button href="{{ route('admin.inventory.categories.create') }}">{{ __('admin.inventory.categories.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.inventory.categories.parent_category'), __('admin.fields.actions')]">
        @forelse ($categories as $category)
            <tr>
                <td class="px-4 py-3">{{ $category->name }}</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $category->parent?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $category)
                            <a href="{{ route('admin.inventory.categories.edit', $category) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $category)
                            <form method="POST" action="{{ route('admin.inventory.categories.destroy', $category) }}" data-confirm="{{ __('admin.inventory.categories.confirm_delete') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:underline">{{ __('common.actions.delete') }}</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="px-4 py-12">
                    <x-empty-state :title="__('admin.inventory.categories.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
