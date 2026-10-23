@extends('layouts.install')

@section('title', 'Requirements')

@section('content')
    <h2 class="text-base font-semibold text-gray-900">System requirements</h2>

    <ul class="mt-4 space-y-1 text-sm">
        @foreach ($checks as $check)
            <li class="flex items-center justify-between border-b border-gray-100 py-1">
                <span>{{ $check['label'] }}</span>
                <span class="{{ $check['passed'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $check['passed'] ? 'OK' : 'Missing' }}
                </span>
            </li>
        @endforeach
    </ul>

    <h2 class="mt-6 text-base font-semibold text-gray-900">Writable directories</h2>
    <ul class="mt-4 space-y-1 text-sm">
        @foreach ($writableChecks as $check)
            <li class="flex items-center justify-between border-b border-gray-100 py-1">
                <span>{{ $check['label'] }}</span>
                <span class="{{ $check['passed'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $check['passed'] ? 'Writable' : 'Not writable' }}
                </span>
            </li>
        @endforeach
        <li class="flex items-center justify-between border-b border-gray-100 py-1">
            <span>.env</span>
            <span class="{{ $envWritable ? 'text-green-600' : 'text-red-600' }}">
                {{ $envWritable ? 'Writable' : 'Not writable' }}
            </span>
        </li>
    </ul>

    <div class="mt-6">
        @if ($canContinue)
            <a href="{{ route('install.database') }}" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                Continue
            </a>
        @else
            <p class="text-sm text-red-700">Resolve the issues above, then refresh this page.</p>
        @endif
    </div>
@endsection
