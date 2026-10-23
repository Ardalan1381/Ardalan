@extends('layouts.install')

@section('title', 'Complete')

@section('content')
    <h2 class="text-base font-semibold text-gray-900">Installation complete</h2>
    <p class="mt-2 text-sm text-gray-600">
        {{ config('app.name') }} is ready. The installer is now locked and cannot be run again.
    </p>

    <p class="mt-4 text-sm text-gray-600">
        Debug mode has been turned off automatically for this live installation.
    </p>

    <p class="mt-4 text-sm text-gray-600">
        Remember to keep a background queue worker and the Reverb server running for notifications
        and real-time updates — see the deployment documentation.
    </p>

    <a href="{{ route('login') }}" class="mt-6 inline-block rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
        Go to login
    </a>
@endsection
