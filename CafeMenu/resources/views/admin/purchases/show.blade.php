@extends('layouts.admin')

@section('admin-content')
    <a href="{{ route('admin.purchases.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.purchases.back_to_purchases') }}</a>

    <div class="mt-2 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $purchase->purchase_number }}</h1>
        <div class="flex items-center gap-2">
            <x-badge>{{ __('admin.purchases.status_'.$purchase->status) }}</x-badge>
            <x-badge :variant="['unpaid' => 'warning', 'partially_paid' => 'info', 'paid' => 'success'][$purchase->payment_status] ?? 'neutral'">
                {{ __('admin.purchases.payment_status_'.$purchase->payment_status) }}
            </x-badge>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('admin.purchases.lines_title')">
                <div class="-mx-5 overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-2 text-start font-medium">{{ __('admin.fields.name') }}</th>
                                <th class="px-2 py-2 text-end font-medium">{{ __('admin.purchases.ordered') }}</th>
                                <th class="px-2 py-2 text-end font-medium">{{ __('admin.purchases.received') }}</th>
                                <th class="px-2 py-2 text-end font-medium">{{ __('admin.purchases.remaining') }}</th>
                                <th class="px-2 py-2 text-end font-medium">{{ __('admin.purchases.unit_cost') }}</th>
                                <th class="px-5 py-2 text-end font-medium">{{ __('admin.purchases.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($purchase->items as $item)
                                <tr>
                                    <td class="px-5 py-2">{{ $item->inventoryItem->name }}</td>
                                    <td class="px-2 py-2 text-end whitespace-nowrap">{{ \App\Support\Quantity::display($item->quantity) }} {{ $item->unit->abbreviation }}</td>
                                    <td class="px-2 py-2 text-end whitespace-nowrap">{{ $item->quantity_received }} {{ $item->unit->abbreviation }}</td>
                                    <td class="px-2 py-2 text-end whitespace-nowrap">{{ bcsub((string) $item->quantity, (string) $item->quantity_received, 4) }} {{ $item->unit->abbreviation }}</td>
                                    <td class="px-2 py-2 text-end whitespace-nowrap">{{ \App\Support\Money::format($item->unit_cost) }}</td>
                                    <td class="px-5 py-2 text-end whitespace-nowrap">{{ \App\Support\Money::format($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end border-t border-gray-100 dark:border-gray-800 pt-4 text-sm">
                    <dl class="w-48 space-y-1">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.purchases.subtotal') }}</dt>
                            <dd>{{ \App\Support\Money::format($purchase->subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between font-medium">
                            <dt>{{ __('admin.purchases.total_cost') }}</dt>
                            <dd>{{ \App\Support\Money::format($purchase->total_cost) }}</dd>
                        </div>
                    </dl>
                </div>
            </x-card>

            @can('receive', $purchase)
                @if ($purchase->status !== 'received' && $purchase->status !== 'cancelled')
                    <x-card :title="__('admin.purchases.receiving_title')">
                        <form method="POST" action="{{ route('admin.purchases.receive', $purchase) }}" class="space-y-3">
                            @csrf

                            @foreach ($purchase->items as $item)
                                @php $remaining = bcsub((string) $item->quantity, (string) $item->quantity_received, 4); @endphp
                                <div class="flex flex-wrap items-end justify-between gap-2 border-b border-gray-100 dark:border-gray-800 pb-2 last:border-b-0">
                                    <div class="text-sm">
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->inventoryItem->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('admin.purchases.remaining') }}: {{ $remaining }} {{ $item->unit->abbreviation }}
                                        </p>
                                    </div>

                                    @if (bccomp($remaining, '0', 4) > 0)
                                        <div class="flex items-center gap-2">
                                            <input type="hidden" name="receipts[{{ $loop->index }}][purchase_item_id]" value="{{ $item->id }}">
                                            <input type="text" inputmode="decimal" name="receipts[{{ $loop->index }}][quantity]" placeholder="{{ __('admin.purchases.receive_quantity') }}" class="w-32 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm shadow-sm">
                                        </div>
                                    @else
                                        <span class="text-xs text-success">{{ __('admin.purchases.no_remaining') }}</span>
                                    @endif
                                </div>
                            @endforeach

                            <x-button type="submit" size="sm">{{ __('admin.purchases.receive_submit') }}</x-button>
                        </form>
                    </x-card>
                @endif
            @endcan
        </div>

        <div class="space-y-6">
            <x-card :title="__('admin.suppliers.title')">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $purchase->supplier?->name }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $purchase->supplier?->phone }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.purchases.purchase_date') }}</dt>
                        <dd>{{ \App\Support\LocalizedDate::format($purchase->purchase_date, 'Y/m/d') }}</dd>
                    </div>
                    @if ($purchase->expected_date)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.purchases.expected_date') }}</dt>
                            <dd>{{ \App\Support\LocalizedDate::format($purchase->expected_date, 'Y/m/d') }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($purchase->notes)
                    <p class="mt-3 border-t border-gray-100 dark:border-gray-800 pt-3 text-sm text-gray-600 dark:text-gray-400">{{ $purchase->notes }}</p>
                @endif
            </x-card>

            @can('updatePaymentStatus', $purchase)
                <x-card :title="__('admin.purchases.update_payment_status')">
                    <form method="POST" action="{{ route('admin.purchases.payment-status', $purchase) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')

                        <x-select name="payment_status" :label="__('admin.purchases.payment_status')">
                            @foreach (['unpaid', 'partially_paid', 'paid'] as $status)
                                <option value="{{ $status }}" @selected($purchase->payment_status === $status)>{{ __('admin.purchases.payment_status_'.$status) }}</option>
                            @endforeach
                        </x-select>

                        <x-button type="submit" size="sm">{{ __('common.actions.save') }}</x-button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
@endsection
