@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.title') }}</h1>

    <form method="GET" action="{{ route('admin.reports.index') }}" class="mt-4 flex items-end gap-3 text-sm">
        <x-input name="from" type="date" :label="__('reports.from')" value="{{ $from->toDateString() }}" />
        <x-input name="to" type="date" :label="__('reports.to')" value="{{ $to->toDateString() }}" />
        <x-button type="submit">{{ __('reports.filter') }}</x-button>
    </form>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.sales_by_day') }}</h2>
        <x-table class="mt-2" :headers="[__('reports.date'), __('reports.orders'), __('reports.total')]">
            @forelse ($salesByPeriod as $row)
                <tr>
                    <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($row->date, 'Y/m/d') }}</td>
                    <td class="px-4 py-3">{{ $row->order_count }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_sales_period') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.product_sales') }}</h2>
        <x-table class="mt-2" :headers="[__('reports.product'), __('reports.quantity'), __('reports.revenue')]">
            @forelse ($popularProducts as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->product_name_snapshot }}</td>
                    <td class="px-4 py-3">{{ $row->total_quantity }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total_revenue) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_sales_period') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.table_sales') }}</h2>
        <x-table class="mt-2" :headers="[__('reports.table'), __('reports.orders'), __('reports.total')]">
            @forelse ($tableSales as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->table_name }}</td>
                    <td class="px-4 py-3">{{ $row->order_count }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_dine_in_sales_period') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.payment_summary') }}</h2>
        <x-table class="mt-2" :headers="[__('reports.method'), __('reports.status'), __('reports.count'), __('reports.total')]">
            @forelse ($paymentSummary as $row)
                <tr>
                    <td class="px-4 py-3">{{ __('payments.methods.'.$row->method) }}</td>
                    <td class="px-4 py-3"><x-badge :status="$row->status">{{ __('payments.status.'.$row->status) }}</x-badge></td>
                    <td class="px-4 py-3">{{ $row->payment_count }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_payments_period') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.category_sales') }}</h2>
        <x-table class="mt-2" :headers="[__('reports.category'), __('reports.quantity'), __('reports.revenue')]">
            @forelse ($categorySales as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->category_name ?? __('reports.uncategorized') }}</td>
                    <td class="px-4 py-3">{{ $row->total_quantity }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total_revenue) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_category_sales_period') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.customer_analytics') }}</h2>
        <x-table class="mt-2" :headers="[__('reports.customer'), __('reports.orders'), __('reports.total_spent')]">
            @forelse ($topCustomers as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->customer_name }}</td>
                    <td class="px-4 py-3">{{ $row->order_count }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total_spent) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_customer_sales_period') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.staff_performance') }}</h2>

        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('reports.staff_orders') }}</h3>
        <x-table class="mt-2" :headers="[__('reports.staff_member'), __('reports.order_count'), __('reports.revenue')]">
            @forelse ($staffOrderPerformance as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->user_name }}</td>
                    <td class="px-4 py-3">{{ $row->order_count }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total_revenue) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_staff_orders_period') }}</td></tr>
            @endforelse
        </x-table>

        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('reports.staff_payments') }}</h3>
        <x-table class="mt-2" :headers="[__('reports.staff_member'), __('reports.payment_count'), __('reports.total')]">
            @forelse ($staffPaymentVolume as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->user_name }}</td>
                    <td class="px-4 py-3">{{ $row->payment_count }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_staff_payments_period') }}</td></tr>
            @endforelse
        </x-table>

        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('reports.closed_shifts') }}</h3>
        <x-table class="mt-2" :headers="[__('reports.staff_member'), __('reports.opened_at'), __('reports.closed_at'), __('reports.expected_cash'), __('reports.closing_cash'), __('reports.difference')]">
            @forelse ($closedShifts as $shift)
                <tr>
                    <td class="px-4 py-3">{{ $shift->user->name }}</td>
                    <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($shift->opened_at) }}</td>
                    <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($shift->closed_at) }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $shift->expected_cash_amount) }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $shift->closing_cash_amount) }}</td>
                    <td class="px-4 py-3 {{ $shift->cash_difference < 0 ? 'text-danger' : ($shift->cash_difference > 0 ? 'text-success' : '') }}">
                        {{ \App\Support\Money::format((int) $shift->cash_difference) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_closed_shifts_period') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.inventory_analytics') }}</h2>

        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat-card :label="__('reports.stock_valuation')" :value="\App\Support\Money::format($stockValuation['total_value'])" />
            <x-stat-card :label="__('reports.waste_cost_period')" :value="\App\Support\Money::format($wasteCost)" />
            <x-stat-card :label="__('reports.low_stock_items')" :value="$lowStockCount" />
        </div>

        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('reports.purchase_spend_by_supplier') }}</h3>
        <x-table class="mt-2" :headers="[__('reports.supplier'), __('reports.purchases'), __('reports.total')]">
            @forelse ($purchaseSpendBySupplier as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->supplier_name }}</td>
                    <td class="px-4 py-3">{{ $row->purchase_count }}</td>
                    <td class="px-4 py-3">{{ \App\Support\Money::format((int) $row->total_spend) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_purchases_period') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('reports.profit_analytics') }}</h2>

        @if ($canViewProfit)
            <x-table class="mt-2" :headers="[__('reports.product'), __('reports.quantity'), __('reports.revenue'), __('reports.cogs'), __('reports.profit')]">
                @forelse ($profitByProduct as $row)
                    <tr>
                        <td class="px-4 py-3">
                            {{ $row->product_name }}
                            @unless ($row->has_recipe)
                                <span class="ms-1 text-xs text-gray-400 dark:text-gray-500">({{ __('reports.no_recipe_configured') }})</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3">{{ $row->quantity }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Money::format($row->revenue) }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Money::format($row->cogs) }}</td>
                        <td class="px-4 py-3 {{ $row->profit < 0 ? 'text-danger' : 'text-success' }}">{{ \App\Support\Money::format($row->profit) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('reports.no_profit_data_period') }}</td></tr>
                @endforelse
            </x-table>
        @else
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('reports.profit_analytics_restricted') }}</p>
        @endif
    </div>
@endsection
