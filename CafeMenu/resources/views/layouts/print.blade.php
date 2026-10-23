<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name'))</title>

        @vite(['resources/css/app.css'])

        <style>
            @media print {
                .no-print { display: none !important; }
                body { background: #fff !important; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900 antialiased">
        @yield('content')
    </body>
</html>
