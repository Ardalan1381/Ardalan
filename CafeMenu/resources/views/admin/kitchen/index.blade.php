@extends('layouts.admin')

@section('focus-mode', '1')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.kitchen.title') }}</h1>
        <p class="text-xs text-gray-500 dark:text-gray-400" data-kds-refresh-note hidden>{{ __('admin.kitchen.auto_refresh') }}</p>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($columns as $status => $orders)
            <section aria-labelledby="kds-col-{{ $status }}" class="flex min-w-0 flex-col rounded-2xl bg-gray-100/70 p-2 dark:bg-gray-900/60">
                <div class="flex items-center justify-between px-2 py-1.5">
                    <h2 id="kds-col-{{ $status }}" class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('orders.status.'.$status) }}</h2>
                    <span class="inline-flex min-w-7 items-center justify-center rounded-full bg-white px-2 py-0.5 text-xs font-semibold tabular-nums text-gray-700 shadow-sm dark:bg-gray-800 dark:text-gray-200">{{ $orders->count() }}</span>
                </div>

                <div class="mt-1 space-y-3">
                    @forelse ($orders as $order)
                        @php
                            $minutes = $order->minutesInCurrentStatus();
                            $delayed = $order->isDelayed($threshold);
                            // Amber once two thirds of the delay threshold has passed.
                            $warning = ! $delayed && $threshold > 0 && $minutes * 3 >= $threshold * 2;
                        @endphp
                        <article
                            data-kds-order
                            data-urgency="{{ $delayed ? 'delayed' : ($warning ? 'warning' : 'normal') }}"
                            @class([
                                'rounded-xl border-s-4 bg-white p-3 shadow-sm dark:bg-gray-900',
                                'border-danger ring-1 ring-danger/30' => $delayed,
                                'border-warning' => $warning,
                                'border-gray-300 dark:border-gray-700' => ! $delayed && ! $warning,
                            ])
                        >
                            <header class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="whitespace-nowrap text-base font-bold tabular-nums text-gray-900 dark:text-gray-100" dir="ltr">{{ $order->order_number }}</p>
                                    <p class="mt-0.5 truncate text-sm text-gray-600 dark:text-gray-400">
                                        {{ $order->table?->name ?? __('orders.type_labels.'.$order->type) }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums',
                                        'bg-danger text-white' => $delayed,
                                        'bg-warning-subtle text-gray-900 dark:text-gray-100' => $warning,
                                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => ! $delayed && ! $warning,
                                    ])>{{ __('admin.kitchen.elapsed_minutes', ['minutes' => $minutes]) }}</span>
                                    @if ($delayed)
                                        <span class="text-xs font-semibold text-danger">{{ __('admin.kitchen.delayed') }}</span>
                                    @endif
                                </div>
                            </header>

                            <ul class="mt-3 space-y-2 text-[15px] leading-snug text-gray-800 dark:text-gray-200">
                                @foreach ($order->items as $item)
                                    <li class="flex gap-2">
                                        <span class="inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-md bg-gray-900 px-1.5 text-sm font-bold tabular-nums text-white dark:bg-gray-100 dark:text-gray-900">{{ $item->quantity }}</span>
                                        <div class="min-w-0">
                                            <span class="font-medium">{{ $item->product_name_snapshot }}</span>
                                            @if ($item->options->isNotEmpty())
                                                <span class="block text-sm text-gray-600 dark:text-gray-400">{{ $item->options->pluck('option_name_snapshot')->join('، ') }}</span>
                                            @endif
                                            @if ($item->item_notes)
                                                <span class="mt-1 block rounded-md bg-warning-subtle px-2 py-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('orders.note_prefix', ['note' => $item->item_notes]) }}</span>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($order->notes)
                                <p class="mt-3 rounded-md bg-warning-subtle px-2 py-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('orders.note_prefix', ['note' => $order->notes]) }}</p>
                            @endif

                            @php
                                $next = ['pending' => 'confirmed', 'confirmed' => 'preparing', 'preparing' => 'ready', 'ready' => 'completed'][$status] ?? null;
                            @endphp

                            @if ($next)
                                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-3" data-kds-advance>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $next }}">
                                    <x-button type="submit" class="min-h-11 w-full text-base">{{ __('admin.kitchen.mark_next', ['status' => __('orders.status.'.$next)]) }}</x-button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <x-empty-state :title="__('admin.kitchen.empty')" />
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Prevent a double tap from submitting the same transition twice
            // (the server would refuse the second one anyway).
            document.querySelectorAll('[data-kds-advance]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    form.querySelector('button[type="submit"]').disabled = true;
                });
            });

            if (window.Echo) {
                window.Echo.private('orders')
                    .listen('.order.placed', () => location.reload())
                    .listen('.order.status-changed', () => location.reload());

                return;
            }

            // No real-time connection (Reverb not running or not configured):
            // fall back to refreshing the board, so the kitchen never sits on
            // a stale screen. Skipped while a form is being submitted.
            document.querySelector('[data-kds-refresh-note]').hidden = false;
            setInterval(function () {
                if (! document.querySelector('[data-kds-advance] button:disabled')) {
                    location.reload();
                }
            }, 20000);
        });
    </script>
@endsection
