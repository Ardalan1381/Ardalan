@props(['label', 'value', 'variant' => 'primary'])

@php
    $iconColors = [
        'primary' => 'bg-primary/10 text-primary',
        'success' => 'bg-success-subtle text-success',
        'info' => 'bg-info-subtle text-info',
        'warning' => 'bg-warning-subtle text-warning',
    ][$variant] ?? 'bg-primary/10 text-primary';
@endphp

<x-card :padded="true" class="flex items-start gap-3">
    @isset($icon)
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconColors }}">
            {{ $icon }}
        </div>
    @endisset
    <div class="min-w-0">
        <p class="truncate text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
        <p class="mt-0.5 truncate text-xl font-bold text-gray-900 dark:text-gray-100">{{ $value }}</p>
    </div>
</x-card>
