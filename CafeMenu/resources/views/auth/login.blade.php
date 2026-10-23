@extends('layouts.app')

@php $business = \App\Models\BusinessSetting::safe(); @endphp

@section('content')
    <div class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-950 p-6">
        <div class="w-full max-w-sm">
            <div class="mb-4 flex justify-end gap-2">
                <x-theme-toggle />
                <x-locale-switcher />
            </div>

            <x-card class="shadow-md">
                <div class="text-center">
                    @if ($business->logo())
                        <img src="{{ $business->logo()->url() }}" alt="{{ $business->name }}" class="mx-auto h-16 w-16 rounded-full object-cover shadow-sm">
                    @else
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a5.25 5.25 0 0 1 6.775-5.025.75.75 0 0 1 .313 1.248l-3.32 3.319c.063.475.276.934.641 1.299.365.365.824.578 1.3.64l3.318-3.319a.75.75 0 0 1 1.248.313 5.25 5.25 0 0 1-5.472 6.756c-1.018-.086-1.87.1-2.309.634L7.344 21.3A3.298 3.298 0 1 1 2.7 16.657l8.684-7.14c.534-.44.72-1.291.634-2.309a5.342 5.342 0 0 1-.068-.858Z" />
                            </svg>
                        </div>
                    @endif
                    <h1 class="mt-3 text-lg font-bold text-gray-900 dark:text-gray-100">{{ $business->name ?: config('app.name') }}</h1>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ config('app.name') }}</p>
                </div>

                @if ($errors->any())
                    <x-alert type="danger" class="mt-5">{{ $errors->first() }}</x-alert>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                    @csrf

                    <x-input name="email" type="email" :label="__('auth.login.email')" value="{{ old('email') }}" required autofocus />

                    <x-input name="password" type="password" :label="__('auth.login.password')" required />

                    <x-checkbox name="remember" :label="__('auth.login.remember_me')" />

                    <x-button type="submit" class="w-full">
                        {{ __('auth.login.submit') }}
                    </x-button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
