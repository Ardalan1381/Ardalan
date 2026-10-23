@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.dashboard') }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.signed_in_as', ['name' => auth()->user()->name, 'role' => auth()->user()->role?->label]) }}</p>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-stat-card :label="__('admin.dashboard.todays_sales')" :value="\App\Support\Money::format($todaySales)" variant="success">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card :label="__('admin.dashboard.orders_today')" :value="$todayOrderCount" variant="primary">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664M6.75 7.5h.008v.008H6.75V7.5Zm0 3h.008v.008H6.75V10.5Z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card :label="__('admin.dashboard.avg_order_value')" :value="\App\Support\Money::format($todayAverageOrderValue)" variant="info">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182.553-.44 1.278-.659 2.003-.659.725 0 1.45.22 2.003.659l.415.33" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card :label="__('admin.dashboard.active_orders')" :value="$activeOrdersCount" variant="warning">
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.dashboard.popular_products') }}</h2>

        <x-table class="mt-2" :headers="[__('admin.dashboard.product'), __('admin.dashboard.quantity_sold'), __('admin.dashboard.revenue')]">
            @forelse ($popularProducts as $row)
                <tr>
                    <td class="px-4 py-2">{{ $row->product_name_snapshot }}</td>
                    <td class="px-4 py-2">{{ $row->total_quantity }}</td>
                    <td class="px-4 py-2">{{ \App\Support\Money::format($row->total_revenue) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('admin.dashboard.no_sales_today') }}</td>
                </tr>
            @endforelse
        </x-table>
    </div>

    @can('reports.view')
        <p class="mt-6 text-sm">
            <a href="{{ route('admin.reports.index') }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.dashboard.view_full_reports') }}</a>
        </p>
    @endcan
@endsection
