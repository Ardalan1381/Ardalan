@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.products') }}</h1>
        @can('create', \App\Models\Product::class)
            <x-button href="{{ route('admin.products.create') }}">{{ __('admin.products.new') }}</x-button>
        @endcan
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.category'), __('admin.fields.price'), __('admin.fields.available'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($products as $product)
            <tr>
                <td class="px-4 py-3">{{ $product->name }}</td>
                <td class="px-4 py-3">{{ $product->category->name }}</td>
                <td class="px-4 py-3">
                    {{ \App\Support\Money::format($product->effectivePrice()) }}
                    @if ($product->discount_price)
                        <span class="text-gray-400 dark:text-gray-500 line-through">{{ \App\Support\Money::format($product->base_price) }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">{{ $product->is_available ? __('common.labels.yes') : __('common.labels.no') }}</td>
                <td class="px-4 py-3"><x-badge :status="$product->is_active ? 'active' : 'inactive'">{{ $product->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $product)
                            <a href="{{ route('admin.products.edit', $product) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $product)
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" data-confirm="{{ __('admin.products.confirm_delete') }}">
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
                <td colspan="6" class="px-4 py-12">
                    <x-empty-state :title="__('admin.products.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $products->links() }}
    </div>
@endsection
