@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.pos.index_title') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.pos.index_subtitle') }}</p>
        </div>
    </div>

    <a
        href="{{ route('admin.pos.counter') }}"
        class="mt-6 flex items-center justify-between gap-3 rounded-xl border border-primary/20 bg-primary/5 px-5 py-4 shadow-sm transition hover:bg-primary/10"
    >
        <div>
            <p class="text-sm font-semibold text-primary">{{ __('admin.pos.counter_link') }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.pos.counter_link_hint') }}</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5 shrink-0 text-primary rtl:rotate-180">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
        </svg>
    </a>

    @if ($sections->isEmpty())
        <x-empty-state :title="__('admin.pos.sections_empty')" class="mt-6" />
    @else
        <div class="mt-8 space-y-8">
            @foreach ($sections as $section)
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $section->name }}</h2>

                    @if ($section->tables->isEmpty())
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.tables.empty') }}</p>
                    @else
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                            @foreach ($section->tables as $table)
                                <a
                                    href="{{ route('admin.pos.table', $table) }}"
                                    class="flex flex-col gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                >
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $table->name }}</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        <x-badge :status="$table->status">
                                            {{ $table->status === 'available' ? __('admin.tables.status_available') : __('admin.tables.status_out_of_service') }}
                                        </x-badge>
                                        @if ($table->sessions->isNotEmpty())
                                            <x-badge variant="warning">{{ __('admin.pos.occupied') }}</x-badge>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
