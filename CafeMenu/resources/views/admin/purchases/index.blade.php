@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.purchases.title') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('admin.inventory.items.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.inventory') }}</a>
            @can('create', \App\Models\Purchase::class)
                <x-button href="{{ route('admin.purchases.create') }}">{{ __('admin.purchases.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.order'), __('admin.fields.date'), __('admin.suppliers.title'), __('admin.fields.status'), __('admin.purchases.payment_status'), __('admin.purchases.total_cost'), __('admin.fields.actions')]">
        @forelse ($purchases as $purchase)
            <tr>
                <td class="px-4 py-3">{{ $purchase->purchase_number }}</td>
                <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($purchase->purchase_date, 'Y/m/d') }}</td>
                <td class="px-4 py-3">{{ $purchase->supplier?->name }}</td>
                <td class="px-4 py-3"><x-badge>{{ __('admin.purchases.status_'.$purchase->status) }}</x-badge></td>
                <td class="px-4 py-3">
                    <x-badge :variant="['unpaid' => 'warning', 'partially_paid' => 'info', 'paid' => 'success'][$purchase->payment_status] ?? 'neutral'">
                        {{ __('admin.purchases.payment_status_'.$purchase->payment_status) }}
                    </x-badge>
                </td>
                <td class="px-4 py-3">{{ \App\Support\Money::format($purchase->total_cost) }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.purchases.show', $purchase) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.view') }}</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-12">
                    <x-empty-state :title="__('admin.purchases.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $purchases->links() }}
    </div>
@endsection
