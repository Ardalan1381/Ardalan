@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-600 px-6 py-12 text-center']) }}>
    @isset($icon)
        <div class="mb-3 text-gray-400 dark:text-gray-500">{{ $icon }}</div>
    @endisset

    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
