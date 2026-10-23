@props(['name', 'label' => null, 'value' => '1', 'hint' => null, 'checked' => false])

<div>
    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input
            type="checkbox"
            name="{{ $name }}"
            value="{{ $value }}"
            @checked($checked)
            {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-2 focus:ring-primary/30']) }}
        >
        {{ $label ?? $slot }}
    </label>

    @if ($hint)
        <p class="ms-6 mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
</div>
