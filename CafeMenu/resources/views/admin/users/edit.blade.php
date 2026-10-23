@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $user->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $user->name) }}" required />

            <x-input name="email" type="email" :label="__('admin.fields.email')" value="{{ old('email', $user->email) }}" required />

            <x-select name="role_id" :label="__('admin.fields.role')" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->label }}</option>
                @endforeach
            </x-select>

            <x-input name="password" type="password" :label="__('admin.users.new_password_optional')" />

            <x-input name="password_confirmation" type="password" :label="__('admin.users.confirm_new_password')" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
