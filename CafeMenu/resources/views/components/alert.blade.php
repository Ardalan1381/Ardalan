@props(['type' => 'info'])

@php
    $styles = [
        'success' => 'bg-success-subtle text-success border-success/20',
        'danger' => 'bg-danger-subtle text-danger border-danger/20',
        'warning' => 'bg-warning-subtle text-warning border-warning/20',
        'info' => 'bg-info-subtle text-info border-info/20',
    ][$type] ?? 'bg-info-subtle text-info border-info/20';
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border px-4 py-3 text-sm $styles"]) }} role="alert">
    {{ $slot }}
</div>
