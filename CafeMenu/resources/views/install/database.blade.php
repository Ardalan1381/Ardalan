@extends('layouts.install')

@section('title', 'Database')

@section('content')
    <h2 class="text-base font-semibold text-gray-900">Database configuration</h2>
    <p class="mt-1 text-sm text-gray-500">Provide credentials for an existing MySQL/MariaDB database and user.</p>

    <form method="POST" action="{{ route('install.database.store') }}" class="mt-4 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700">Host</label>
            <input type="text" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Port</label>
            <input type="number" name="db_port" value="{{ old('db_port', 3306) }}" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Database name</label>
            <input type="text" name="db_database" value="{{ old('db_database') }}" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" name="db_username" value="{{ old('db_username') }}" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="db_password" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
            Test connection &amp; continue
        </button>
    </form>
@endsection
