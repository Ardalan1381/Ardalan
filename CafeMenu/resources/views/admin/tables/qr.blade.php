@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.tables.qr_title', ['table' => $table->name]) }}</h1>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($locales as $locale)
            <x-card class="text-center print:border-0">
                <img src="{{ route('admin.tables.qr.png', [$table, 'lang' => $locale]) }}" alt="QR code for {{ $table->name }} ({{ $locale }})" class="mx-auto h-48 w-48">
                <p class="mt-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $table->name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $table->section->name }} · {{ __('admin.qr_languages.'.$locale) }}</p>

                <div class="mt-3 flex flex-wrap justify-center gap-2 text-xs print:hidden">
                    <x-button href="{{ route('admin.tables.qr.png', [$table, 'lang' => $locale]) }}" download="table-{{ $table->id }}-qr-{{ $locale }}.png" size="sm">
                        {{ __('admin.tables.download_png') }}
                    </x-button>
                    <x-button href="{{ route('admin.tables.qr.svg', [$table, 'lang' => $locale]) }}" download="table-{{ $table->id }}-qr-{{ $locale }}.svg" size="sm">
                        {{ __('admin.tables.download_svg') }}
                    </x-button>
                </div>
            </x-card>
        @endforeach
    </div>

    <div class="mt-4 flex flex-wrap gap-3 text-sm print:hidden">
        <x-button variant="secondary" type="button" onclick="window.print()">
            {{ __('admin.tables.print') }}
        </x-button>

        @can('update', $table)
            <form method="POST" action="{{ route('admin.tables.qr.regenerate', $table) }}" data-confirm="{{ __('admin.tables.confirm_regenerate') }}">
                @csrf
                <x-button variant="danger" type="submit">
                    {{ __('admin.tables.regenerate') }}
                </x-button>
            </form>
        @endcan
    </div>
@endsection
