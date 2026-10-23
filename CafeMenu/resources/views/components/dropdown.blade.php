@props(['align' => 'end'])

<details class="relative" data-dropdown>
    <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
        {{ $trigger }}
    </summary>

    <div class="absolute z-20 mt-2 min-w-48 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-1 shadow-lg {{ $align === 'end' ? 'end-0' : 'start-0' }}">
        {{ $slot }}
    </div>
</details>
