@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.inventory') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            @can('inventory.view')
                <a href="{{ route('admin.inventory.units.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.manage_units') }}</a>
                <a href="{{ route('admin.inventory.categories.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.manage_categories') }}</a>
            @endcan
            @can('suppliers.manage')
                <a href="{{ route('admin.suppliers.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.manage_suppliers') }}</a>
            @endcan
            @can('purchases.view')
                <a href="{{ route('admin.purchases.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.manage_purchases') }}</a>
            @endcan
            @can('inventory.adjust')
                <a href="{{ route('admin.stock-adjustments.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.manage_adjustments') }}</a>
                <a href="{{ route('admin.waste-records.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.manage_waste') }}</a>
                <a href="{{ route('admin.purchase-returns.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.manage_returns') }}</a>
                <a href="{{ route('admin.stock-counts.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.inventory.manage_counts') }}</a>
            @endcan
            @can('create', \App\Models\InventoryItem::class)
                <x-button href="{{ route('admin.inventory.items.create') }}">{{ __('admin.inventory.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.sku'), __('admin.fields.category'), __('admin.fields.unit'), __('admin.inventory.current_stock'), __('admin.inventory.average_cost'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($items as $item)
            <tr>
                <td class="px-4 py-3">{{ $item->name }}</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $item->sku ?? '—' }}</td>
                <td class="px-4 py-3">{{ $item->category?->name ?? __('admin.inventory.no_category') }}</td>
                <td class="px-4 py-3">{{ $item->baseUnit->abbreviation }}</td>
                <td class="px-4 py-3">
                    {{ \App\Support\Quantity::display($item->current_stock) }}
                    @if ($item->isLowStock())
                        <x-badge variant="danger">{{ __('admin.inventory.low_stock') }}</x-badge>
                    @endif
                </td>
                <td class="px-4 py-3">{{ $item->average_cost !== null ? \App\Support\Money::format($item->average_cost) : '—' }}</td>
                <td class="px-4 py-3"><x-badge :status="$item->is_active ? 'active' : 'inactive'">{{ $item->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $item)
                            <a href="{{ route('admin.inventory.items.edit', $item) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $item)
                            <form method="POST" action="{{ route('admin.inventory.items.destroy', $item) }}" data-confirm="{{ __('admin.inventory.confirm_delete') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:underline">{{ __('common.actions.delete') }}</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="px-4 py-12">
                    <x-empty-state :title="__('admin.inventory.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $items->links() }}
    </div>
@endsection
