<button
    type="button"
    data-theme-toggle
    aria-label="{{ __('common.theme.toggle') }}"
    {{ $attributes->merge(['class' => 'inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700']) }}
>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5 dark:hidden">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m8.25-9H21M3 12h1.5m14.36-6.36-1.06 1.06M6.7 17.3l-1.06 1.06m0-12.72L6.7 6.7m10.6 10.6 1.06 1.06M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
    </svg>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="hidden h-5 w-5 dark:block">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
    </svg>
</button>
