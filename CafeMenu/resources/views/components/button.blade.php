@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-primary text-white hover:opacity-90 focus-visible:outline-primary',
        'secondary' => 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 focus-visible:outline-gray-400',
        'danger' => 'bg-danger text-white hover:opacity-90 focus-visible:outline-danger',
        'ghost' => 'bg-transparent text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus-visible:outline-gray-400',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-base',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition '
        .'disabled:cursor-not-allowed disabled:opacity-50 '
        .'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 '
        .($variants[$variant] ?? $variants['primary']).' '
        .($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
