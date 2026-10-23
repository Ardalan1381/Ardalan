@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.stock_adjustments.title') }}</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.inventory.items.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.inventory') }}</a>
            @can('create', \App\Models\StockAdjustment::class)
                <x-button href="{{ route('admin.stock-adjustments.create') }}">{{ __('admin.stock_adjustments.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.stock_adjustments.reference_number'), __('admin.fields.date'), __('admin.stock_adjustments.reason'), __('admin.fields.name'), __('admin.fields.user')]">
        @forelse ($adjustments as $adjustment)
            @foreach ($adjustment->items as $line)
                <tr>
                    @if ($loop->first)
                        <td class="px-4 py-3" rowspan="{{ $adjustment->items->count() }}">{{ $adjustment->reference_number }}</td>
                        <td class="px-4 py-3" rowspan="{{ $adjustment->items->count() }}">{{ \App\Support\LocalizedDate::format($adjustment->created_at) }}</td>
                        <td class="px-4 py-3" rowspan="{{ $adjustment->items->count() }}">{{ __('admin.stock_adjustments.reason_'.$adjustment->reason) }}</td>
                    @endif
                    <td class="px-4 py-3">
                        {{ $line->inventoryItem->name }}
                        <span class="{{ str_starts_with((string) $line->quantity_delta, '-') ? 'text-danger' : 'text-success' }}">
                            ({{ $line->quantity_delta }})
                        </span>
                    </td>
                    @if ($loop->first)
                        <td class="px-4 py-3" rowspan="{{ $adjustment->items->count() }}">{{ $adjustment->createdBy?->name ?? '—' }}</td>
                    @endif
                </tr>
            @endforeach
        @empty
            <tr>
                <td colspan="5" class="px-4 py-12">
                    <x-empty-state :title="__('admin.stock_adjustments.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $adjustments->links() }}
    </div>
@endsection
