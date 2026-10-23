@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.orders') }}</h1>

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.orders.index') }}" class="rounded-full px-3 py-1.5 font-medium transition {{ $statusFilter === '' ? 'bg-primary text-white' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800' }}">{{ __('admin.orders_index.all') }}</a>
        @foreach (['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled', 'rejected'] as $status)
            <a href="{{ route('admin.orders.index', ['status' => $status]) }}" class="rounded-full px-3 py-1.5 font-medium transition {{ $statusFilter === $status ? 'bg-primary text-white' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                {{ __('orders.status.'.$status) }}
            </a>
        @endforeach
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.order'), __('orders.type'), __('admin.fields.table'), __('orders.total'), __('admin.fields.status'), __('admin.orders_index.placed'), __('admin.fields.actions')]">
        @forelse ($orders as $order)
            <tr>
                <td class="px-4 py-3">{{ $order->order_number }}</td>
                <td class="px-4 py-3">{{ __('orders.type_labels.'.$order->type) }}</td>
                <td class="px-4 py-3">{{ $order->table?->name ?? '—' }}</td>
                <td class="px-4 py-3">{{ \App\Support\Money::format($order->grand_total) }}</td>
                <td class="px-4 py-3"><x-badge :status="$order->status">{{ __('orders.status.'.$order->status) }}</x-badge></td>
                <td class="px-4 py-3">{{ \App\Support\LocalizedDate::diffForHumans($order->created_at) }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.orders.show', $order) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.orders_index.view') }}</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-12">
                    <x-empty-state :title="__('admin.orders_index.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
@endsection
