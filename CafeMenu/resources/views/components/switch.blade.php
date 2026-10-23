@props(['name', 'label' => null, 'value' => '1'])

<label class="inline-flex cursor-pointer items-center gap-2">
    <span class="relative inline-flex h-6 w-11 shrink-0 items-center">
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="absolute inset-0 rounded-full bg-gray-300 dark:bg-gray-600 transition peer-checked:bg-primary"></span>
        <span class="absolute start-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></span>
    </span>
    @if ($label)
        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
    @endif
</label>
