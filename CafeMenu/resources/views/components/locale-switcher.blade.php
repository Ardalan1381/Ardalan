@php
    $locales = ['en' => 'English', 'fa' => 'فارسی', 'ar' => 'العربية'];
@endphp

<x-dropdown align="end" {{ $attributes }}>
    <x-slot:trigger>
        <span class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
            {{ $locales[app()->getLocale()] ?? app()->getLocale() }}
        </span>
    </x-slot:trigger>

    @foreach ($locales as $code => $label)
        <form method="POST" action="{{ route('locale.update', $code) }}">
            @csrf
            <button
                type="submit"
                class="block w-full px-4 py-2 text-start text-sm {{ app()->getLocale() === $code ? 'font-semibold text-primary' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
            >
                {{ $label }}
            </button>
        </form>
    @endforeach
</x-dropdown>
