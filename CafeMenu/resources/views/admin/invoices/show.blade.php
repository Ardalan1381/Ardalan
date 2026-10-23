@extends('layouts.print')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="mx-auto max-w-2xl p-6">
        <div class="no-print mb-4 flex items-center justify-between">
            <a href="{{ route('admin.orders.show', $invoice->order) }}" class="text-sm text-gray-500">{{ __('orders.admin.back_to_orders') }}</a>
            <button type="button" onclick="window.print()" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white">{{ __('admin.invoices.print') }}</button>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
            @if ($invoice->template?->show_logo && \App\Models\BusinessSetting::safe()->logo())
                <img src="{{ \App\Models\BusinessSetting::safe()->logo()->url() }}" alt="" class="mx-auto mb-4 h-14 w-14 rounded object-cover">
            @endif

            @if ($invoice->template?->header_html)
                <div class="mb-4 text-center text-sm">{!! $invoice->template->header_html !!}</div>
            @endif

            <div class="flex items-start justify-between text-sm">
                <div>
                    <p class="text-lg font-semibold text-gray-900">{{ $invoice->invoice_number }}</p>
                    <p class="text-gray-500">{{ \App\Support\LocalizedDate::format($invoice->issued_at) }}</p>
                </div>
                <div class="text-end text-gray-500">
                    @if ($invoice->table_name_snapshot)
                        <p>{{ __('orders.table') }}: {{ $invoice->table_name_snapshot }}</p>
                    @endif
                    @if ($invoice->waiter_name_snapshot)
                        <p>{{ __('admin.invoices.waiter') }}: {{ $invoice->waiter_name_snapshot }}</p>
                    @endif
                </div>
            </div>

            @if ($invoice->customer_name_snapshot)
                <p class="mt-2 text-sm text-gray-500">
                    {{ __('orders.admin.customer') }}: {{ $invoice->customer_name_snapshot }}
                    @if ($invoice->customer_phone_snapshot)
                        &middot; {{ $invoice->customer_phone_snapshot }}
                    @endif
                </p>
            @endif

            <table class="mt-6 w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                        <th class="py-2 text-start font-medium">{{ __('admin.invoices.item') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('admin.invoices.line_total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($invoice->lineItems as $line)
                        <tr>
                            <td class="py-2">
                                <p class="font-medium text-gray-900">{{ $line->quantity }}&times; {{ $line->product_name_snapshot }}</p>
                                @if ($line->option_summary_snapshot)
                                    <p class="text-xs text-gray-500">{{ $line->option_summary_snapshot }}</p>
                                @endif
                            </td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($line->line_total_snapshot) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4 space-y-1 border-t border-gray-200 pt-4 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">{{ __('orders.subtotal') }}</span><span>{{ \App\Support\Money::format($invoice->subtotal) }}</span></div>
                @if ($invoice->discount_total > 0)
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('orders.discount') }}</span><span>-{{ \App\Support\Money::format($invoice->discount_total) }}</span></div>
                @endif
                @if ($invoice->tax_total > 0)
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('orders.tax') }}</span><span>{{ \App\Support\Money::format($invoice->tax_total) }}</span></div>
                @endif
                @if ($invoice->service_charge_total > 0)
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('orders.service_charge') }}</span><span>{{ \App\Support\Money::format($invoice->service_charge_total) }}</span></div>
                @endif
                <div class="flex justify-between text-base font-semibold text-gray-900"><span>{{ __('orders.total') }}</span><span>{{ \App\Support\Money::format($invoice->grand_total) }}</span></div>
            </div>

            @if ($invoice->template?->footer_html)
                <div class="mt-6 text-center text-xs text-gray-500">{!! $invoice->template->footer_html !!}</div>
            @endif
        </div>
    </div>
@endsection
