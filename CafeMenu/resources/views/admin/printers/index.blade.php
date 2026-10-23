@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.printers.title') }}</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.print-routes.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.printers.manage_routes') }}</a>
            @can('create', \App\Models\Printer::class)
                <x-button href="{{ route('admin.printers.create') }}">{{ __('admin.printers.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.printers.connection'), __('admin.printers.paper_width'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($printers as $printer)
            <tr>
                <td class="px-4 py-3">{{ $printer->name }}</td>
                <td class="px-4 py-3">
                    {{ __('admin.printers.connection_labels.'.$printer->connection_type) }}
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($printer->connection_type === 'network')
                            {{ $printer->ip_address }}@if($printer->port):{{ $printer->port }}@endif
                        @else
                            {{ $printer->device_path }}
                        @endif
                    </span>
                </td>
                <td class="px-4 py-3">{{ $printer->paper_width }}</td>
                <td class="px-4 py-3"><x-badge :status="$printer->is_active ? 'active' : 'inactive'">{{ $printer->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $printer)
                            <a href="{{ route('admin.printers.edit', $printer) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $printer)
                            <form method="POST" action="{{ route('admin.printers.destroy', $printer) }}" data-confirm="{{ __('admin.printers.confirm_delete') }}">
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
                    <x-empty-state :title="__('admin.printers.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
