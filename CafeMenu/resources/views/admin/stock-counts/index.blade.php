@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.stock_counts.title') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('admin.inventory.items.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.inventory') }}</a>
            @can('create', \App\Models\StockCount::class)
                <x-button href="{{ route('admin.stock-counts.create') }}">{{ __('admin.stock_counts.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.stock_adjustments.reference_number'), __('admin.fields.date'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($counts as $count)
            <tr>
                <td class="px-4 py-3">{{ $count->reference_number }}</td>
                <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($count->created_at) }}</td>
                <td class="px-4 py-3">
                    <x-badge :variant="$count->status === 'completed' ? 'success' : 'warning'">{{ __('admin.stock_counts.status_'.$count->status) }}</x-badge>
                </td>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.stock-counts.show', $count) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.view') }}</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-12">
                    <x-empty-state :title="__('admin.stock_counts.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $counts->links() }}
    </div>
@endsection
