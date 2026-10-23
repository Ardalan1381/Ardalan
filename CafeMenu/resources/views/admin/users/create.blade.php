@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.users.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-input name="email" type="email" :label="__('admin.fields.email')" value="{{ old('email') }}" required />

            <x-select name="role_id" :label="__('admin.fields.role')" required>
                <option value="">{{ __('admin.users.select_role') }}</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->label }}</option>
                @endforeach
            </x-select>

            <x-input name="password" type="password" :label="__('admin.users.password')" required />

            <x-input name="password_confirmation" type="password" :label="__('admin.users.password_confirmation')" required />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
