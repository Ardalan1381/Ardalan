@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.print_routes.title') }}</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.printers.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.print_routes.manage_printers') }}</a>
            @can('create', \App\Models\PrintRoute::class)
                <x-button href="{{ route('admin.print-routes.create') }}">{{ __('admin.print_routes.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.type'), __('admin.print_routes.category'), __('admin.printers.title'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($printRoutes as $printRoute)
            <tr>
                <td class="px-4 py-3">{{ __('admin.print_routes.document_type_labels.'.$printRoute->document_type) }}</td>
                <td class="px-4 py-3">{{ $printRoute->category?->name ?? __('admin.print_routes.any_category') }}</td>
                <td class="px-4 py-3">{{ $printRoute->printer->name }}</td>
                <td class="px-4 py-3"><x-badge :status="$printRoute->is_active ? 'active' : 'inactive'">{{ $printRoute->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $printRoute)
                            <a href="{{ route('admin.print-routes.edit', $printRoute) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $printRoute)
                            <form method="POST" action="{{ route('admin.print-routes.destroy', $printRoute) }}" data-confirm="{{ __('admin.print_routes.confirm_delete') }}">
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
                <td colspan="5" class="px-4 py-12">
                    <x-empty-state :title="__('admin.print_routes.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
