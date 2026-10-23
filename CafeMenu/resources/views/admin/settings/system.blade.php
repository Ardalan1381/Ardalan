@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.system_settings') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.settings.system.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input
                name="pagination_per_page"
                type="number"
                :label="__('admin.system_settings.page_size')"
                value="{{ old('pagination_per_page', $paginationPerPage) }}"
                min="5"
                max="100"
                required
                :hint="__('admin.system_settings.page_size_hint')"
            />

            <x-button type="submit">{{ __('common.actions.save') }}</x-button>
        </form>
    </x-card>
@endsection
