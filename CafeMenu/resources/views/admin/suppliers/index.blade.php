@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.suppliers.title') }}</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.inventory.items.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.inventory') }}</a>
            @can('create', \App\Models\Supplier::class)
                <x-button href="{{ route('admin.suppliers.create') }}">{{ __('admin.suppliers.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.suppliers.contact_name'), __('admin.fields.phone'), __('admin.fields.email'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($suppliers as $supplier)
            <tr>
                <td class="px-4 py-3">{{ $supplier->name }}</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $supplier->contact_name ?? '—' }}</td>
                <td class="px-4 py-3">{{ $supplier->phone ?? '—' }}</td>
                <td class="px-4 py-3">{{ $supplier->email ?? '—' }}</td>
                <td class="px-4 py-3"><x-badge :status="$supplier->is_active ? 'active' : 'inactive'">{{ $supplier->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $supplier)
                            <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $supplier)
                            <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}" data-confirm="{{ __('admin.suppliers.confirm_delete') }}">
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
                    <x-empty-state :title="__('admin.suppliers.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $suppliers->links() }}
    </div>
@endsection
