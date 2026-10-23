@props(['id', 'title' => null])

<dialog id="{{ $id }}" data-modal class="w-full max-w-md rounded-xl border-0 p-0 shadow-xl backdrop:bg-gray-900/50">
    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-3">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        <button type="button" data-modal-close class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Close">&times;</button>
    </div>

    <div class="p-5">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex justify-end gap-2 border-t border-gray-100 dark:border-gray-800 px-5 py-3">
            {{ $footer }}
        </div>
    @endisset
</dialog>
