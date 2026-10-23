@extends('layouts.install')

@section('title', 'Migrate')

@section('content')
    <h2 class="text-base font-semibold text-gray-900">Set up the database</h2>
    <p class="mt-1 text-sm text-gray-500">
        @if ($alreadyMigrated)
            The database tables already exist. You can re-run this safely; it won't lose data.
        @else
            This creates all required tables and seeds the default roles and permissions.
        @endif
    </p>

    <form method="POST" action="{{ route('install.migrate.run') }}" class="mt-4">
        @csrf
        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
            Run migrations
        </button>
    </form>
@endsection
