@props(['variant' => 'text'])

@php
    $shapes = [
        'text' => 'h-4 rounded',
        'block' => 'h-24 rounded-lg',
        'circle' => 'h-10 w-10 rounded-full',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'animate-pulse bg-gray-200 dark:bg-gray-700 '.($shapes[$variant] ?? $shapes['text'])]) }}></div>
