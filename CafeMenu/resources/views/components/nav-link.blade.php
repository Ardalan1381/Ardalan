@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => 'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition '.($active ? 'bg-primary/10 font-medium text-primary' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700')]) }}
>
    @isset($icon)
        <span class="shrink-0 {{ $active ? 'text-primary' : 'text-gray-400 dark:text-gray-500' }}">{{ $icon }}</span>
    @endisset
    <span class="truncate">{{ $slot }}</span>
</a>
