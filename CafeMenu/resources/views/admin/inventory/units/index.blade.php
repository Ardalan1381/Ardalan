@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.inventory.units.title') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('admin.inventory.items.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.inventory') }}</a>
            @can('create', \App\Models\InventoryUnit::class)
                <x-button href="{{ route('admin.inventory.units.create') }}">{{ __('admin.inventory.units.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.inventory.units.abbreviation'), __('admin.inventory.units.type'), __('admin.inventory.units.base_factor'), __('admin.fields.actions')]">
        @forelse ($units as $unit)
            <tr>
                <td class="px-4 py-3">{{ $unit->name }}</td>
                <td class="px-4 py-3">{{ $unit->abbreviation }}</td>
                <td class="px-4 py-3">{{ __('admin.inventory.units.type_'.$unit->type) }}</td>
                <td class="px-4 py-3">{{ $unit->base_factor }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $unit)
                            <a href="{{ route('admin.inventory.units.edit', $unit) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $unit)
                            <form method="POST" action="{{ route('admin.inventory.units.destroy', $unit) }}" data-confirm="{{ __('admin.inventory.units.confirm_delete') }}">
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
                    <x-empty-state :title="__('admin.inventory.units.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
