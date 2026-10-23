@extends('layouts.install')

@section('title', 'Owner account')

@section('content')
    <h2 class="text-base font-semibold text-gray-900">Create the owner account</h2>
    <p class="mt-1 text-sm text-gray-500">This account has full access to the admin panel.</p>

    <form method="POST" action="{{ route('install.owner.store') }}" class="mt-4 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Confirm password</label>
            <input type="password" name="password_confirmation" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
            Create account
        </button>
    </form>
@endsection
