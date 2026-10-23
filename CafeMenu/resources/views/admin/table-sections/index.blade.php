@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.table_sections') }}</h1>
        @can('create', \App\Models\TableSection::class)
            <x-button href="{{ route('admin.table-sections.create') }}">{{ __('admin.table_sections.new') }}</x-button>
        @endcan
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.tables'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($tableSections as $section)
            <tr>
                <td class="px-4 py-3">{{ $section->name }}</td>
                <td class="px-4 py-3">{{ $section->tables_count }}</td>
                <td class="px-4 py-3"><x-badge :status="$section->is_active ? 'active' : 'inactive'">{{ $section->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $section)
                            <a href="{{ route('admin.table-sections.edit', $section) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $section)
                            <form method="POST" action="{{ route('admin.table-sections.destroy', $section) }}" data-confirm="{{ __('admin.table_sections.confirm_delete') }}">
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
                <td colspan="4" class="px-4 py-12">
                    <x-empty-state :title="__('admin.table_sections.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
