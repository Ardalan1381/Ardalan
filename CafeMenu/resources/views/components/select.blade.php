@props([
    'name',
    'label' => null,
    'hint' => null,
    'required' => false,
])

@php
    $errorMessage = $errors->first($name);
    $inputId = $attributes->get('id', $name);
@endphp

<div>
    @if ($label)
        <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $inputId }}"
        name="{{ $name }}"
        @if ($required) required @endif
        {{ $attributes->except('id')->merge([
            'class' => 'mt-1 block w-full rounded-lg border shadow-sm text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 '
                .($errorMessage ? 'border-danger focus:ring-danger/30' : 'border-gray-300 dark:border-gray-600 focus:ring-primary/30 focus:border-primary'),
        ]) }}
    >{{ $slot }}</select>

    @if ($hint && ! $errorMessage)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    @if ($errorMessage)
        <p class="mt-1 text-xs text-danger">{{ $errorMessage }}</p>
    @endif
</div>
