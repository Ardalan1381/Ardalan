@props(['status' => null, 'variant' => null])

@php
    $statusVariants = [
        // orders.status
        'pending' => 'warning',
        'confirmed' => 'info',
        'preparing' => 'info',
        'ready' => 'success',
        'completed' => 'success',
        'cancelled' => 'danger',
        'rejected' => 'danger',
        // payments.status / orders.payment_status
        'paid' => 'success',
        'failed' => 'danger',
        'refunded' => 'info',
        // tables.status
        'available' => 'success',
        'out_of_service' => 'danger',
        // table_sessions.status
        'open' => 'success',
        'closed' => 'neutral',
        // waiter_calls.status
        'resolved' => 'success',
        // generic active/inactive
        'active' => 'success',
        'inactive' => 'neutral',
    ];

    $resolved = $variant ?? $statusVariants[$status] ?? 'neutral';

    $styles = [
        'success' => 'bg-success-subtle text-success',
        'warning' => 'bg-warning-subtle text-warning',
        'danger' => 'bg-danger-subtle text-danger',
        'info' => 'bg-info-subtle text-info',
        'neutral' => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
    ][$resolved] ?? 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $styles"]) }}>
    {{ $slot }}
</span>
