@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.tables') }}</h1>
        @can('create', \App\Models\Table::class)
            <x-button href="{{ route('admin.tables.create') }}">{{ __('admin.tables.new') }}</x-button>
        @endcan
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.section'), __('admin.fields.capacity'), __('admin.fields.status'), __('common.labels.active'), __('admin.fields.actions')]">
        @forelse ($tables as $table)
            <tr>
                <td class="px-4 py-3">{{ $table->name }}</td>
                <td class="px-4 py-3">{{ $table->section->name }}</td>
                <td class="px-4 py-3">{{ $table->capacity ?? '—' }}</td>
                <td class="px-4 py-3"><x-badge :status="$table->status">{{ $table->status === 'available' ? __('admin.tables.status_available') : __('admin.tables.status_out_of_service') }}</x-badge></td>
                <td class="px-4 py-3">{{ $table->is_active ? __('common.labels.yes') : __('common.labels.no') }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('view', $table)
                            <a href="{{ route('admin.tables.qr', $table) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.tables.qr') }}</a>
                            <a href="{{ route('admin.tables.session', $table) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.tables.session') }}</a>
                        @endcan
                        @can('update', $table)
                            <a href="{{ route('admin.tables.edit', $table) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $table)
                            <form method="POST" action="{{ route('admin.tables.destroy', $table) }}" data-confirm="{{ __('admin.tables.confirm_delete') }}">
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
                <td colspan="6" class="px-4 py-12">
                    <x-empty-state :title="__('admin.tables.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $tables->links() }}
    </div>
@endsection
