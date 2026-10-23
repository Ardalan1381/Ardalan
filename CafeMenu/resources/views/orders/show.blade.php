@extends('layouts.app')

@section('title', __('orders.title').' '.$order->order_number)

@section('content')
    @vite('resources/js/order-tracking.js')

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 pb-16">
        <div class="flex items-center justify-between bg-white dark:bg-gray-900 px-4 py-3 shadow-sm">
            <a href="{{ route('menu.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 rtl:rotate-180">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __('menu.back_to_menu_short') }}
            </a>
            <div class="flex items-center gap-2">
                <x-theme-toggle />
                <x-locale-switcher />
            </div>
        </div>

        <div class="mx-auto max-w-2xl px-4">
            <div class="py-8 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('orders.title') }}</p>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{{ $order->order_number }}</h1>
                <x-badge
                    status="{{ $order->status }}"
                    data-order-status
                    data-order-uuid="{{ $order->uuid }}"
                    class="mt-3 px-3 py-1 text-sm"
                >
                    {{ __('orders.status.'.$order->status) }}
                </x-badge>
            </div>

            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($order->items as $item)
                    <div class="flex items-start justify-between p-4 text-sm">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->quantity }}&times; {{ $item->product_name_snapshot }}</p>
                            @if ($item->options->isNotEmpty())
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->options->pluck('option_name_snapshot')->join(', ') }}</p>
                            @endif
                            @if ($item->item_notes)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('orders.note_prefix', ['note' => $item->item_notes]) }}</p>
                            @endif
                        </div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($item->line_total) }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 space-y-1.5 rounded-xl bg-white dark:bg-gray-900 p-4 text-sm shadow-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('orders.subtotal') }}</span>
                    <span>{{ \App\Support\Money::format($order->subtotal) }}</span>
                </div>
                @if ($order->tax_total > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('orders.tax') }}</span>
                        <span>{{ \App\Support\Money::format($order->tax_total) }}</span>
                    </div>
                @endif
                @if ($order->service_charge_total > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('orders.service_charge') }}</span>
                        <span>{{ \App\Support\Money::format($order->service_charge_total) }}</span>
                    </div>
                @endif
                <div class="flex justify-between border-t border-gray-100 dark:border-gray-800 pt-1.5 text-base font-bold text-gray-900 dark:text-gray-100">
                    <span>{{ __('orders.total') }}</span>
                    <span class="text-primary">{{ \App\Support\Money::format($order->grand_total) }}</span>
                </div>
            </div>

            <dl class="mt-4 space-y-1.5 rounded-xl bg-white dark:bg-gray-900 p-4 text-sm text-gray-500 dark:text-gray-400 shadow-sm">
                <div class="flex justify-between">
                    <dt>{{ __('orders.type') }}</dt>
                    <dd class="font-medium text-gray-700 dark:text-gray-300">{{ __('orders.type_labels.'.$order->type) }}</dd>
                </div>
                @if ($order->table)
                    <div class="flex justify-between">
                        <dt>{{ __('orders.table') }}</dt>
                        <dd class="font-medium text-gray-700 dark:text-gray-300">{{ $order->table->name }} &middot; {{ $order->table->section->name }}</dd>
                    </div>
                @endif
                @if ($order->notes)
                    <div class="flex justify-between">
                        <dt>{{ __('orders.notes') }}</dt>
                        <dd class="font-medium text-gray-700 dark:text-gray-300">{{ $order->notes }}</dd>
                    </div>
                @endif
            </dl>

            @php $hasPendingCashPayment = $order->payments->contains(fn ($payment) => $payment->method === 'cash' && $payment->status === 'pending'); @endphp

            @if ($hasPendingCashPayment)
                <div class="mt-4 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('payments.cash_on_pickup.title') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('payments.cash_on_pickup.description', ['amount' => \App\Support\Money::format($order->balanceDue())]) }}
                    </p>
                </div>
            @elseif ($order->balanceDue() > 0 && ! empty($availableGateways))
                <div class="mt-4 rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('payments.pay_online.title') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('payments.balance_due') }}: <span class="font-semibold text-primary">{{ \App\Support\Money::format($order->balanceDue()) }}</span>
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($availableGateways as $gateway)
                            <x-button href="{{ route('payments.initiate', [$order, $gateway]) }}" size="sm">
                                {{ __('payments.gateways.'.$gateway) }}
                            </x-button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
