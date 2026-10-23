@extends('layouts.app')

@section('content')
    <div class="flex min-h-screen items-center justify-center p-6">
        <div class="text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('payments.pay_online.redirecting') }}</p>
            <form id="gateway-redirect-form" method="POST" action="{{ $url }}">
                @foreach ($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            </form>
        </div>
    </div>
    <script>document.getElementById('gateway-redirect-form').submit();</script>
@endsection
