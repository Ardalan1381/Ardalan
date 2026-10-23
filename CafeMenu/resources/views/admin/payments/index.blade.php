@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.payments') }}</h1>

    <x-table class="mt-6" :headers="[__('admin.fields.order'), __('admin.fields.method'), __('admin.fields.amount'), __('admin.fields.status'), __('admin.payments_index.recorded'), __('admin.fields.actions')]">
        @forelse ($payments as $payment)
            <tr>
                <td class="px-4 py-3">{{ $payment->order->order_number }}</td>
                <td class="px-4 py-3">
                    {{ __('payments.methods.'.$payment->method) }}
                    @if ($payment->gateway)
                        <span class="text-gray-400 dark:text-gray-500">&middot; {{ __('payments.gateways.'.$payment->gateway) }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">{{ \App\Support\Money::format($payment->amount) }}</td>
                <td class="px-4 py-3"><x-badge :status="$payment->status">{{ __('payments.status.'.$payment->status) }}</x-badge></td>
                <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($payment->created_at) }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.orders.show', $payment->order) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.payments_index.view_order') }}</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-12">
                    <x-empty-state :title="__('admin.payments_index.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $payments->links() }}
    </div>
@endsection
