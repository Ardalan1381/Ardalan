@extends('layouts.admin')

@php
    $nextStatuses = [
        'pending' => ['confirmed', 'rejected', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'rejected' => [],
    ][$order->status] ?? [];
@endphp

@section('admin-content')
    <a href="{{ route('admin.orders.index') }}" class="text-sm text-gray-500 dark:text-gray-400">{{ __('orders.admin.back_to_orders') }}</a>

    <div class="mt-4 flex items-start justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $order->order_number }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('orders.type_labels.'.$order->type) }} &middot; {{ \App\Support\LocalizedDate::format($order->created_at) }}</p>
        </div>
        <x-badge :status="$order->status">{{ __('orders.status.'.$order->status) }}</x-badge>
    </div>

    @if ($errors->any())
        <x-alert type="danger" class="mt-4">{{ $errors->first() }}</x-alert>
    @endif

    @if (! empty($nextStatuses))
        <div class="mt-4 flex flex-wrap items-center gap-2">
            @foreach ($nextStatuses as $next)
                <form method="POST" action="{{ route('admin.orders.status', $order) }}" data-confirm="{{ __('orders.admin.confirm_move_to', ['status' => __('orders.status.'.$next)]) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $next }}">
                    <x-button type="submit" size="sm">{{ __('orders.admin.mark_status', ['status' => __('orders.status.'.$next)]) }}</x-button>
                </form>
            @endforeach
        </div>
    @endif

    <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
        @foreach ($order->items as $item)
            <div class="flex items-start justify-between p-3 text-sm">
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

    <div class="mt-4 space-y-1 text-sm">
        <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">{{ __('orders.subtotal') }}</span><span>{{ \App\Support\Money::format($order->subtotal) }}</span></div>
        <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">{{ __('orders.tax') }}</span><span>{{ \App\Support\Money::format($order->tax_total) }}</span></div>
        <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">{{ __('orders.service_charge') }}</span><span>{{ \App\Support\Money::format($order->service_charge_total) }}</span></div>
        <div class="flex justify-between text-base font-semibold text-gray-900 dark:text-gray-100"><span>{{ __('orders.total') }}</span><span>{{ \App\Support\Money::format($order->grand_total) }}</span></div>
    </div>

    <dl class="mt-6 space-y-1 text-sm text-gray-500 dark:text-gray-400">
        @if ($order->table)
            <div class="flex justify-between"><dt>{{ __('orders.table') }}</dt><dd>{{ $order->table->name }} &middot; {{ $order->table->section->name }}</dd></div>
        @endif
        @if ($order->customer_name)
            <div class="flex justify-between"><dt>{{ __('orders.admin.customer') }}</dt><dd>{{ $order->customer_name }} &middot; {{ $order->customer_phone }}</dd></div>
        @endif
        @if ($order->delivery_address)
            <div class="flex justify-between"><dt>{{ __('orders.admin.delivery_address') }}</dt><dd>{{ $order->delivery_address }}</dd></div>
        @endif
        @if ($order->notes)
            <div class="flex justify-between"><dt>{{ __('orders.notes') }}</dt><dd>{{ $order->notes }}</dd></div>
        @endif
    </dl>

    <div class="mt-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.payments') }}</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('payments.balance_due') }}: {{ \App\Support\Money::format($order->balanceDue()) }}
            </span>
        </div>

        <div class="mt-2 rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($order->payments as $payment)
                <div class="p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                {{ __('payments.methods.'.$payment->method) }}
                                @if ($payment->gateway)
                                    ({{ __('payments.gateways.'.$payment->gateway) }})
                                @endif
                                &middot; {{ \App\Support\Money::format($payment->amount) }}
                                @if ($payment->billSplitPart)
                                    &middot; {{ __('admin.bill_splits.payment_for_part', ['label' => $payment->billSplitPart->label]) }}
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ \App\Support\LocalizedDate::format($payment->created_at) }}
                                @if ($payment->recordedBy)
                                    {{ __('orders.admin.by', ['name' => $payment->recordedBy->name]) }}
                                @endif
                                @if ($payment->reference)
                                    &middot; {{ __('orders.admin.ref', ['ref' => $payment->reference]) }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-badge :status="$payment->status">{{ __('payments.status.'.$payment->status) }}</x-badge>
                            @can('updateStatus', $payment)
                                @if ($payment->status === 'pending')
                                    <form method="POST" action="{{ route('admin.payments.status', $payment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="paid">
                                        <button type="submit" class="text-xs text-gray-600 dark:text-gray-400 hover:underline">{{ __('payments.mark_paid') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.payments.status', $payment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="failed">
                                        <button type="submit" class="text-xs text-danger hover:underline">{{ __('payments.mark_failed') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>

                    @if ($payment->refunds->isNotEmpty())
                        <div class="mt-2 space-y-1 border-t border-gray-100 dark:border-gray-800 pt-2">
                            @foreach ($payment->refunds as $refund)
                                <div class="text-xs text-gray-500 dark:text-gray-400" data-refund-status="{{ $refund->status }}">
                                    <p>
                                        {{ __('payments.refund') }}: {{ \App\Support\Money::format($refund->amount) }}
                                        <span @class([
                                            'ms-1 inline-flex items-center rounded-full px-2 py-0.5 font-medium',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' => $refund->status === 'pending',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' => $refund->status === 'completed',
                                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $refund->status === 'failed',
                                        ])>{{ __('payments.refund_settlement.statuses.'.$refund->status) }}</span>
                                        @if ($refund->reason)
                                            &mdash; {{ $refund->reason }}
                                        @endif
                                        &middot; {{ __('orders.admin.by', ['name' => $refund->processedBy?->name ?? '—']) }}
                                        &middot; {{ \App\Support\LocalizedDate::format($refund->processed_at) }}
                                        @if ($refund->resolved_at)
                                            &middot; {{ __('payments.refund_settlement.resolved_by', ['name' => $refund->resolvedBy?->name ?? '—']) }}
                                            @if ($refund->external_reference)
                                                (<span dir="ltr">{{ $refund->external_reference }}</span>)
                                            @endif
                                        @endif
                                    </p>

                                    @if ($refund->isPending())
                                        <p class="mt-1 text-amber-700 dark:text-amber-300">{{ __('payments.refund_settlement.pending_hint') }}</p>
                                        @can('refund', $payment)
                                            <form method="POST" action="{{ route('admin.refunds.resolve', $refund) }}" class="mt-1 flex flex-wrap items-end gap-2" data-confirm="{{ __('payments.refund_settlement.confirm_settled_prompt') }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="outcome" value="completed">
                                                <label class="sr-only" for="refund-ref-{{ $refund->id }}">{{ __('payments.refund_settlement.external_reference') }}</label>
                                                <input id="refund-ref-{{ $refund->id }}" type="text" name="external_reference" placeholder="{{ __('payments.refund_settlement.external_reference') }}" class="w-44 rounded-lg border-gray-300 dark:border-gray-600 text-xs shadow-sm">
                                                <button type="submit" class="font-medium text-emerald-700 dark:text-emerald-300 hover:underline">{{ __('payments.refund_settlement.confirm_settled') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.refunds.resolve', $refund) }}" class="mt-1">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="outcome" value="failed">
                                                <button type="submit" class="text-danger hover:underline">{{ __('payments.refund_settlement.mark_not_settled') }}</button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @can('refund', $payment)
                        @if ($payment->status === 'paid' && $payment->refundableAmount() > 0)
                            <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="mt-2 flex flex-wrap items-end gap-2 border-t border-gray-100 dark:border-gray-800 pt-2" data-confirm="{{ __('payments.confirm_refund') }}">
                                @csrf
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('payments.amount') }}</label>
                                    <input type="number" name="amount" value="{{ $payment->refundableAmount() }}" min="1" max="{{ $payment->refundableAmount() }}" required class="mt-1 w-28 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('payments.refund_method') }}</label>
                                    <select name="refund_method" class="mt-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                        <option value="original_payment_method">{{ __('payments.refund_methods.original_payment_method') }}</option>
                                        <option value="cash">{{ __('payments.refund_methods.cash') }}</option>
                                        <option value="store_credit">{{ __('payments.refund_methods.store_credit') }}</option>
                                    </select>
                                </div>
                                <div class="min-w-40 flex-1">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('payments.reason') }}</label>
                                    <input type="text" name="reason" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                </div>
                                <button type="submit" class="text-xs text-danger hover:underline">{{ __('payments.refund') }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            @empty
                <p class="p-3 text-sm text-gray-500 dark:text-gray-400">{{ __('payments.no_payments') }}</p>
            @endforelse
        </div>

        @can('create', \App\Models\Payment::class)
            @if ($order->balanceDue() > 0 && $order->billSplits->isEmpty())
                <form method="POST" action="{{ route('admin.orders.payments.store', $order) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.fields.method') }}</label>
                        <select name="method" class="mt-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                            <option value="cash">{{ __('payments.methods.cash') }}</option>
                            <option value="card">{{ __('payments.methods.card') }}</option>
                            <option value="online">{{ __('payments.methods.online') }}</option>
                            <option value="pay_later">{{ __('payments.methods.pay_later') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.fields.amount') }}</label>
                        <input type="number" name="amount" value="{{ $order->balanceDue() }}" min="1" required class="mt-1 w-32 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.fields.status') }}</label>
                        <select name="status" class="mt-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                            <option value="paid">{{ __('payments.paid_now') }}</option>
                            <option value="pending">{{ __('payments.pending_confirmation') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('payments.reference') }}</label>
                        <input type="text" name="reference" class="mt-1 w-32 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                    </div>
                    <x-button type="submit" size="sm">{{ __('payments.record_payment') }}</x-button>
                </form>
            @endif
        @endcan
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.bill_splits.title') }}</h2>

        @if ($billSplit)
            <div class="mt-2 flex items-center justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">{{ __('admin.bill_splits.type_labels.'.$billSplit->split_type) }}</span>
                <x-badge :status="$billSplit->status === 'settled' ? 'paid' : 'pending'">{{ __('admin.bill_splits.status.'.$billSplit->status) }}</x-badge>
            </div>

            <div class="mt-2 space-y-3">
                @foreach ($billSplit->parts as $part)
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $part->label }}</p>
                            <x-badge :status="$part->status === 'paid' ? 'paid' : 'pending'">{{ __('payments.status.'.$part->status) }}</x-badge>
                        </div>

                        @if ($part->items->isNotEmpty())
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $part->items->map(fn ($item) => $item->orderItem->product_name_snapshot)->join(', ') }}
                            </p>
                        @endif

                        <div class="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ __('admin.bill_splits.amount_due') }}: {{ \App\Support\Money::format($part->amount_due) }}</span>
                            <span>{{ __('admin.bill_splits.amount_paid') }}: {{ \App\Support\Money::format($part->amount_paid) }}</span>
                        </div>

                        @can('recordPayment', $billSplit)
                            @if ($part->status !== 'paid')
                                <form method="POST" action="{{ route('admin.bill-split-parts.payments.store', $part) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                    @csrf
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.fields.method') }}</label>
                                        <select name="method" class="mt-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                            <option value="cash">{{ __('payments.methods.cash') }}</option>
                                            <option value="card">{{ __('payments.methods.card') }}</option>
                                            <option value="online">{{ __('payments.methods.online') }}</option>
                                            <option value="pay_later">{{ __('payments.methods.pay_later') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.fields.amount') }}</label>
                                        <input type="number" name="amount" value="{{ $part->balanceDue() }}" min="1" required class="mt-1 w-28 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.fields.status') }}</label>
                                        <select name="status" class="mt-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                            <option value="paid">{{ __('payments.paid_now') }}</option>
                                            <option value="pending">{{ __('payments.pending_confirmation') }}</option>
                                        </select>
                                    </div>
                                    <x-button type="submit" size="sm">{{ __('payments.record_payment') }}</x-button>
                                </form>
                            @endif
                        @endcan
                    </div>
                @endforeach
            </div>
        @elseif ($order->balanceDue() > 0)
            @can('create', \App\Models\BillSplit::class)
                <a href="{{ route('admin.orders.bill-split.create', $order) }}" class="mt-2 inline-block text-sm text-primary hover:underline">{{ __('admin.bill_splits.split_this_bill') }}</a>
            @endcan
        @else
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.bill_splits.none') }}</p>
        @endif
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.invoices.title') }}</h2>

        <div class="mt-2 text-sm">
            @if ($order->invoice)
                @can('view', $order->invoice)
                    <a href="{{ route('admin.invoices.show', $order->invoice) }}" class="text-primary hover:underline">{{ __('admin.invoices.view_invoice', ['number' => $order->invoice->invoice_number]) }}</a>
                @endcan
            @else
                @can('create', \App\Models\Invoice::class)
                    <form method="POST" action="{{ route('admin.orders.invoice.generate', $order) }}">
                        @csrf
                        <x-button type="submit" size="sm">{{ __('admin.invoices.generate') }}</x-button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.printing.title') }}</h2>

        @can('view', $order)
            @if ($activePrinters->isNotEmpty())
                <form method="POST" action="{{ route('admin.orders.print-receipt', $order) }}" class="mt-2 flex flex-wrap items-end gap-2 text-sm">
                    @csrf
                    @if ($activePrinters->count() > 1)
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('admin.printing.printer') }}</label>
                            <select name="printer_id" class="mt-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                                @foreach ($activePrinters as $printer)
                                    <option value="{{ $printer->id }}">{{ $printer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="printer_id" value="{{ $activePrinters->first()->id }}">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.printing.printer') }}: {{ $activePrinters->first()->name }}</p>
                    @endif
                    <x-button type="submit" size="sm">{{ __('admin.printing.print_receipt') }}</x-button>
                </form>
            @else
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.printing.no_printers') }}
                    @can('printers.manage')
                        <a href="{{ route('admin.printers.create') }}" class="text-primary hover:underline">{{ __('admin.printing.add_printer') }}</a>
                    @endcan
                </p>
            @endif
        @endcan

        @if ($order->printJobs->isNotEmpty())
            <div class="mt-3 space-y-1">
                @foreach ($order->printJobs->sortByDesc('id') as $job)
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('admin.printing.document_types.'.$job->document_type) }}
                        &middot; {{ $job->printer->name }}
                        &middot; <x-badge :variant="$job->status === 'sent' ? 'success' : ($job->status === 'failed' ? 'danger' : 'warning')">{{ __('admin.printing.job_status.'.$job->status) }}</x-badge>
                        ({{ __('admin.printing.attempts', ['count' => $job->attempts]) }})
                        @if ($job->error_message)
                            &middot; {{ $job->error_message }}
                        @endif
                    </p>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('orders.admin.status_history') }}</h2>
        <ul class="mt-2 space-y-1 text-sm text-gray-500 dark:text-gray-400">
            @foreach ($order->statusHistory as $entry)
                <li>
                    {{ \App\Support\LocalizedDate::format($entry->created_at) }} &mdash;
                    {{ $entry->from_status ? __('orders.admin.transition', ['from' => __('orders.status.'.$entry->from_status), 'to' => __('orders.status.'.$entry->to_status)]) : __('orders.admin.created_as', ['status' => __('orders.status.'.$entry->to_status)]) }}
                    @if ($entry->changedBy)
                        {{ __('orders.admin.by', ['name' => $entry->changedBy->name]) }}
                    @endif
                    @if ($entry->note)
                        ({{ $entry->note }})
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
