<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('cafe-theme');
                    var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (dark) document.documentElement.classList.add('dark');
                } catch (e) {}
            })();
        </script>

        <title>@yield('title', config('app.name'))</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @php $theme = \App\Models\BusinessSetting::safe(); @endphp
        <style>
            :root {
                --color-primary: {{ $theme->theme_color_primary ?? '#111827' }};
                --color-secondary: {{ $theme->theme_color_secondary ?? '#6366F1' }};
            }
        </style>
        <script>
            window.CAFE = {
                currency: @json($theme->currency ?? 'USD'),
                currencyMinorUnits: @json($theme->currency_minor_units ?? 2),
            };
            window.__translations = {
                js: @json(__('js-messages')),
                order_status: @json(__('orders.status')),
            };
        </script>
    </head>
    <body class="min-h-screen bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
        @yield('content')

        <x-toast-container />
    </body>
</html>
