@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $customer->name }}</h1>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $customer->name) }}" required />

                <x-input name="phone" :label="__('admin.fields.phone')" value="{{ old('phone', $customer->phone) }}" />

                <x-input name="email" type="email" :label="__('admin.fields.email')" value="{{ old('email', $customer->email) }}" />

                <x-select name="customer_group_id" :label="__('admin.fields.group')">
                    <option value="">{{ __('admin.customers.no_group') }}</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected(old('customer_group_id', $customer->customer_group_id) == $group->id)>{{ $group->name }}</option>
                    @endforeach
                </x-select>

                <x-textarea name="notes" :label="__('common.labels.description')" rows="3">{{ old('notes', $customer->notes) }}</x-textarea>

                <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
            </form>
        </x-card>

        <div class="space-y-6">
            <x-card :title="__('admin.customers.stats_title')">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.customers.total_spent') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($stats['total_spent']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.customers.order_count') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $stats['order_count'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.customers.average_order_value') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($stats['average_order_value']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('admin.customers.last_order_at') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $stats['last_order_at'] ? \App\Support\LocalizedDate::diffForHumans($stats['last_order_at']) : __('admin.customers.never_ordered') }}
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card :title="__('admin.customers.recent_orders')">
                @forelse ($recentOrders as $order)
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 py-2 text-sm last:border-b-0 last:pb-0">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $order->order_number }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\LocalizedDate::diffForHumans($order->created_at) }}</p>
                        </div>
                        <div class="text-end">
                            <x-badge :status="$order->status">{{ __('orders.status.'.$order->status) }}</x-badge>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\Money::format($order->grand_total) }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.customers.no_orders') }}</p>
                @endforelse
            </x-card>

            <x-card :title="__('admin.loyalty.ledger_title')">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.loyalty.balance') }}</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $customer->loyalty_points_balance }}</span>
                </div>

                <div class="mt-4 -mx-5 overflow-x-auto">
                    <table class="w-full text-start text-xs">
                        <thead>
                            <tr class="border-y border-gray-100 dark:border-gray-800 text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-2 text-start font-medium">{{ __('admin.fields.date') }}</th>
                                <th class="px-2 py-2 text-start font-medium">{{ __('admin.fields.type') }}</th>
                                <th class="px-2 py-2 text-end font-medium">{{ __('admin.fields.points') }}</th>
                                <th class="px-2 py-2 text-end font-medium">{{ __('admin.loyalty.balance') }}</th>
                                <th class="px-5 py-2 text-start font-medium">{{ __('common.labels.description') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($loyaltyEntries as $entry)
                                <tr>
                                    <td class="px-5 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ \App\Support\LocalizedDate::format($entry->created_at) }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap">{{ __('admin.loyalty.type_labels.'.$entry->type) }}</td>
                                    <td class="px-2 py-2 text-end whitespace-nowrap {{ $entry->points >= 0 ? 'text-success' : 'text-danger' }}">{{ $entry->points >= 0 ? '+'.$entry->points : $entry->points }}</td>
                                    <td class="px-2 py-2 text-end whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $entry->resulting_balance }}</td>
                                    <td class="px-5 py-2 text-gray-500 dark:text-gray-400">{{ $entry->notes }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('admin.loyalty.no_entries') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('loyalty.manage')
                    <form method="POST" action="{{ route('admin.customers.loyalty.adjust', $customer) }}" class="mt-4 space-y-3 border-t border-gray-100 dark:border-gray-800 pt-4">
                        @csrf
                        @method('PATCH')

                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.loyalty.adjust_balance') }}</p>

                        <x-input name="points" type="number" :label="__('admin.loyalty.adjust_points')" value="{{ old('points') }}" required />
                        <x-input name="notes" :label="__('admin.loyalty.adjust_notes')" value="{{ old('notes') }}" />

                        <x-button type="submit">{{ __('admin.loyalty.adjust_submit') }}</x-button>
                    </form>
                @endcan
            </x-card>
        </div>
    </div>
@endsection
