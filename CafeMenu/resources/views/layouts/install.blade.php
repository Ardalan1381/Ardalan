<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'Install') - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
        <div class="mx-auto max-w-xl px-4 py-10">
            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ config('app.name') }} — Installation</h1>

            @if (session('error'))
                <div class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                @yield('content')
            </div>
        </div>
    </body>
</html>
