@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.menu_qr.title') }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.menu_qr.points_to', ['url' => $url]) }}</p>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($locales as $locale)
            <x-card class="text-center print:border-0">
                <img src="{{ route('admin.qr.menu.png', ['lang' => $locale]) }}" alt="Public menu QR code ({{ $locale }})" class="mx-auto h-48 w-48">
                <p class="mt-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('admin.qr_languages.'.$locale) }}</p>

                <div class="mt-3 flex flex-wrap justify-center gap-2 text-xs print:hidden">
                    <x-button href="{{ route('admin.qr.menu.png', ['lang' => $locale]) }}" download="menu-qr-{{ $locale }}.png" size="sm">{{ __('admin.tables.download_png') }}</x-button>
                    <x-button href="{{ route('admin.qr.menu.svg', ['lang' => $locale]) }}" download="menu-qr-{{ $locale }}.svg" size="sm">{{ __('admin.tables.download_svg') }}</x-button>
                </div>
            </x-card>
        @endforeach
    </div>

    <div class="mt-4 flex flex-wrap gap-3 text-sm print:hidden">
        <x-button variant="secondary" type="button" onclick="window.print()">{{ __('admin.tables.print') }}</x-button>
    </div>
@endsection
