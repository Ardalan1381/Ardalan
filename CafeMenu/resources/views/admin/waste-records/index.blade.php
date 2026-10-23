@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.waste.title') }}</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.inventory.items.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.inventory') }}</a>
            @can('create', \App\Models\WasteRecord::class)
                <x-button href="{{ route('admin.waste-records.create') }}">{{ __('admin.waste.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.date'), __('admin.fields.name'), __('admin.inventory.quantity'), __('admin.waste.reason'), __('admin.fields.user')]">
        @forelse ($wasteRecords as $waste)
            <tr>
                <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($waste->wasted_at) }}</td>
                <td class="px-4 py-3">{{ $waste->inventoryItem->name }}</td>
                <td class="px-4 py-3">{{ $waste->quantity }} {{ $waste->inventoryItem->baseUnit->abbreviation }}</td>
                <td class="px-4 py-3">{{ __('admin.waste.reason_'.$waste->reason) }}</td>
                <td class="px-4 py-3">{{ $waste->createdBy?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-12">
                    <x-empty-state :title="__('admin.waste.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $wasteRecords->links() }}
    </div>
@endsection
