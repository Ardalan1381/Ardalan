@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500">{!! __('pagination.previous') !!}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{!! __('pagination.previous') !!}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ms-3 inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{!! __('pagination.next') !!}</a>
            @else
                <span class="ms-3 inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500">{!! __('pagination.next') !!}</span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <div class="inline-flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-300 dark:text-gray-600" aria-hidden="true">&laquo;</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="{{ __('pagination.previous') }}">&laquo;</a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="inline-flex h-9 w-9 items-center justify-center text-sm text-gray-400 dark:text-gray-500">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-sm font-medium text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="{{ __('pagination.next') }}">&raquo;</a>
                @else
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-300 dark:text-gray-600" aria-hidden="true">&raquo;</span>
                @endif
            </div>
        </div>
    </nav>
@endif
