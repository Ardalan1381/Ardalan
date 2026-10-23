@props(['title' => null, 'padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm']) }}>
    @if ($title)
        <div class="border-b border-gray-100 dark:border-gray-800 px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        </div>
    @endif

    <div class="{{ $padded ? 'p-5' : '' }}">
        {{ $slot }}
    </div>
</div>
