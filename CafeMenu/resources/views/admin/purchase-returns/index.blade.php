@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.purchases.returns.title') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('admin.inventory.items.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.inventory') }}</a>
            @can('create', \App\Models\PurchaseReturn::class)
                <x-button href="{{ route('admin.purchase-returns.create') }}">{{ __('admin.purchases.returns.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.stock_adjustments.reference_number'), __('admin.fields.date'), __('admin.suppliers.title'), __('admin.purchases.returns.total_amount'), __('admin.purchases.returns.reason')]">
        @forelse ($returns as $return)
            <tr>
                <td class="px-4 py-3">{{ $return->reference_number }}</td>
                <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($return->created_at) }}</td>
                <td class="px-4 py-3">{{ $return->supplier?->name }}</td>
                <td class="px-4 py-3">{{ \App\Support\Money::format($return->total_amount) }}</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $return->reason ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-12">
                    <x-empty-state :title="__('admin.purchases.returns.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $returns->links() }}
    </div>
@endsection
