@extends('layouts.admin')

@section('admin-content')
    <a href="{{ route('admin.stock-counts.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.stock_counts.back_to_counts') }}</a>

    <div class="mt-2 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $count->reference_number }}</h1>
        <x-badge :variant="$count->status === 'completed' ? 'success' : 'warning'">{{ __('admin.stock_counts.status_'.$count->status) }}</x-badge>
    </div>

    @if ($count->notes)
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $count->notes }}</p>
    @endif

    <x-card class="mt-6" :title="__('admin.inventory.title')">
        @if ($count->status === 'draft')
            <form method="POST" action="{{ route('admin.stock-counts.submit', $count) }}">
                @csrf
                @method('PATCH')

                <div class="-mx-5 overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-2 text-start font-medium">{{ __('admin.fields.name') }}</th>
                                <th class="px-2 py-2 text-end font-medium">{{ __('admin.stock_counts.expected_quantity') }}</th>
                                <th class="px-2 py-2 text-end font-medium">{{ __('admin.stock_counts.counted_quantity') }}</th>
                                <th class="px-5 py-2 text-end font-medium">{{ __('admin.stock_counts.variance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($count->items as $line)
                                <tr>
                                    <td class="px-5 py-2">{{ $line->inventoryItem->name }}</td>
                                    <td class="px-2 py-2 text-end whitespace-nowrap">{{ $line->expected_quantity }} {{ $line->inventoryItem->baseUnit->abbreviation }}</td>
                                    <td class="px-2 py-2 text-end">
                                        <input type="hidden" name="counted[{{ $loop->index }}][stock_count_item_id]" value="{{ $line->id }}">
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            name="counted[{{ $loop->index }}][counted_quantity]"
                                            value="{{ $line->counted_quantity }}"
                                            class="w-28 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-end text-sm shadow-sm"
                                        >
                                    </td>
                                    <td class="px-5 py-2 text-end whitespace-nowrap">
                                        @if ($line->variance !== null)
                                            <x-badge :variant="bccomp((string) $line->variance, '0', 4) === 0 ? 'neutral' : (bccomp((string) $line->variance, '0', 4) > 0 ? 'success' : 'danger')">
                                                {{ $line->variance }}
                                            </x-badge>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-button type="submit" size="sm" class="mt-4">{{ __('admin.stock_counts.submit_counts') }}</x-button>
            </form>

            @can('complete', $count)
                <form method="POST" action="{{ route('admin.stock-counts.complete', $count) }}" data-confirm="{{ __('admin.stock_counts.confirm_complete') }}" class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit" variant="danger" size="sm">{{ __('admin.stock_counts.complete') }}</x-button>
                </form>
            @endcan
        @else
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-2 text-start font-medium">{{ __('admin.fields.name') }}</th>
                            <th class="px-2 py-2 text-end font-medium">{{ __('admin.stock_counts.expected_quantity') }}</th>
                            <th class="px-2 py-2 text-end font-medium">{{ __('admin.stock_counts.counted_quantity') }}</th>
                            <th class="px-5 py-2 text-end font-medium">{{ __('admin.stock_counts.variance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($count->items as $line)
                            <tr>
                                <td class="px-5 py-2">{{ $line->inventoryItem->name }}</td>
                                <td class="px-2 py-2 text-end whitespace-nowrap">{{ $line->expected_quantity }} {{ $line->inventoryItem->baseUnit->abbreviation }}</td>
                                <td class="px-2 py-2 text-end whitespace-nowrap">{{ $line->counted_quantity ?? '—' }}</td>
                                <td class="px-5 py-2 text-end whitespace-nowrap">
                                    @if ($line->variance !== null)
                                        <x-badge :variant="bccomp((string) $line->variance, '0', 4) === 0 ? 'neutral' : (bccomp((string) $line->variance, '0', 4) > 0 ? 'success' : 'danger')">
                                            {{ $line->variance }}
                                        </x-badge>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
