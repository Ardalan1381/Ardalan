@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.kitchen.title') }}</h1>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($columns as $status => $orders)
            <div>
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('orders.status.'.$status) }}</h2>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $orders->count() }}</span>
                </div>

                <div class="mt-2 space-y-3">
                    @forelse ($orders as $order)
                        @php $delayed = $order->isDelayed($threshold); @endphp
                        <div class="rounded-lg border {{ $delayed ? 'border-danger' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-900 p-3 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $order->order_number }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $order->table?->name ?? __('orders.type_labels.'.$order->type) }}
                                    </p>
                                </div>
                                <x-badge :variant="$delayed ? 'danger' : 'neutral'">
                                    {{ $delayed ? __('admin.kitchen.delayed') : __('admin.kitchen.elapsed_minutes', ['minutes' => $order->status_changed_at?->diffInMinutes(now()) ?? 0]) }}
                                </x-badge>
                            </div>

                            <ul class="mt-2 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                                @foreach ($order->items as $item)
                                    <li>
                                        <span class="font-medium">{{ $item->quantity }}&times;</span> {{ $item->product_name_snapshot }}
                                        @if ($item->options->isNotEmpty())
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $item->options->pluck('option_name_snapshot')->join(', ') }}</span>
                                        @endif
                                        @if ($item->item_notes)
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('orders.note_prefix', ['note' => $item->item_notes]) }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            @php
                                $next = ['pending' => 'confirmed', 'confirmed' => 'preparing', 'preparing' => 'ready', 'ready' => 'completed'][$status] ?? null;
                            @endphp

                            @if ($next)
                                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $next }}">
                                    <x-button type="submit" size="sm" class="w-full">{{ __('admin.kitchen.mark_next', ['status' => __('orders.status.'.$next)]) }}</x-button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <x-empty-state :title="__('admin.kitchen.empty')" />
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (! window.Echo) {
                return;
            }

            window.Echo.private('orders')
                .listen('.order.placed', () => location.reload())
                .listen('.order.status-changed', () => location.reload());
        });
    </script>
@endsection
