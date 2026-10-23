@extends('layouts.admin')

@section('admin-content')
    <a href="{{ route('admin.orders.show', $order) }}" class="text-sm text-gray-500 dark:text-gray-400">{{ __('orders.admin.back_to_orders') }}</a>

    <h1 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.bill_splits.title') }} — {{ $order->order_number }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.bill_splits.total') }}: {{ \App\Support\Money::format($order->grand_total) }}</p>

    @if ($errors->any())
        <x-alert type="danger" class="mt-4">{{ $errors->first() }}</x-alert>
    @endif

    <x-card class="mt-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.orders.bill-split.store', $order) }}" class="space-y-6">
            @csrf

            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.bill_splits.split_type') }}</p>
                <div class="mt-2 space-y-2">
                    @foreach (['by_item' => __('admin.bill_splits.type_by_item_hint'), 'by_amount' => __('admin.bill_splits.type_by_amount_hint'), 'even' => __('admin.bill_splits.type_even_hint')] as $type => $hint)
                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" name="split_type" value="{{ $type }}" class="mt-0.5 h-4 w-4 text-primary" @checked(old('split_type', 'by_item') === $type) required>
                            <span>
                                <span class="font-medium">{{ __('admin.bill_splits.type_labels.'.$type) }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.bill_splits.parts') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.bill_splits.parts_hint') }}</p>

                <div class="mt-2 space-y-2">
                    @for ($slot = 1; $slot <= 4; $slot++)
                        <div class="flex items-center gap-2">
                            <span class="w-16 shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.bill_splits.part_number', ['number' => $slot]) }}</span>
                            <input type="text" name="parts[{{ $slot }}][label]" value="{{ old("parts.$slot.label", __('admin.bill_splits.default_part_label', ['number' => $slot])) }}" placeholder="{{ __('admin.bill_splits.part_label_placeholder') }}" class="block w-48 rounded-lg border border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                            <input type="number" name="parts[{{ $slot }}][amount]" min="0" value="{{ old("parts.$slot.amount") }}" placeholder="{{ __('admin.bill_splits.amount_placeholder') }}" class="block w-32 rounded-lg border border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                        </div>
                    @endfor
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.bill_splits.unused_part_hint') }}</p>
            </div>

            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.bill_splits.assign_items') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.bill_splits.assign_items_hint') }}</p>

                <div class="mt-2 rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between p-3 text-sm">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->quantity }}&times; {{ $item->product_name_snapshot }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\Money::format($item->line_total - $item->discount_total) }}</p>
                            </div>
                            <select name="item_assignment[{{ $item->id }}]" class="rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                <option value="">{{ __('admin.bill_splits.unassigned') }}</option>
                                @for ($slot = 1; $slot <= 4; $slot++)
                                    <option value="{{ $slot }}" @selected(old("item_assignment.{$item->id}") == $slot)>{{ __('admin.bill_splits.part_number', ['number' => $slot]) }}</option>
                                @endfor
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            <x-button type="submit">{{ __('admin.bill_splits.create_split') }}</x-button>
        </form>
    </x-card>
@endsection
