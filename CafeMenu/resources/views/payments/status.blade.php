@extends('layouts.app')

@php
    $variant = match ($status) {
        'success' => 'success',
        'pending' => 'warning',
        'cancelled' => 'warning',
        default => 'danger',
    };

    $iconColors = [
        'success' => 'bg-success-subtle text-success',
        'warning' => 'bg-warning-subtle text-warning',
        'danger' => 'bg-danger-subtle text-danger',
    ][$variant];
@endphp

@section('title', __('payments.status_page.'.$status.'.title'))

@section('content')
    <div class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-950 p-6">
        <x-card class="w-full max-w-sm text-center shadow-md">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full {{ $iconColors }}">
                @if ($variant === 'success')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-8 w-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                @elseif ($variant === 'warning')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-8 w-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.28 2.25h17.8a1.5 1.5 0 0 0 1.28-2.25L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z" />
                    </svg>
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-8 w-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                @endif
            </div>

            <h1 class="mt-4 text-lg font-bold text-gray-900 dark:text-gray-100">{{ __('payments.status_page.'.$status.'.title') }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('payments.status_page.'.$status.'.description') }}</p>

            @if (isset($order))
                <x-button href="{{ route('orders.show', $order) }}" class="mt-6 w-full">{{ __('payments.status_page.back_to_order') }}</x-button>
            @else
                <x-button href="{{ route('menu.index') }}" class="mt-6 w-full">{{ __('menu.back_to_menu') }}</x-button>
            @endif
        </x-card>
    </div>
@endsection
