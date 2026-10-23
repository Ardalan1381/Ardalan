@props(['name', 'label' => null, 'value'])

<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input
        type="radio"
        name="{{ $name }}"
        value="{{ $value }}"
        {{ $attributes->merge(['class' => 'h-4 w-4 border-gray-300 dark:border-gray-600 text-primary focus:ring-2 focus:ring-primary/30']) }}
    >
    {{ $label ?? $slot }}
</label>
