@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.suppliers.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.suppliers.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-input name="contact_name" :label="__('admin.suppliers.contact_name')" value="{{ old('contact_name') }}" />

            <x-input name="phone" :label="__('admin.fields.phone')" value="{{ old('phone') }}" />

            <x-input name="email" type="email" :label="__('admin.fields.email')" value="{{ old('email') }}" />

            <x-textarea name="address" :label="__('admin.suppliers.address')" rows="2">{{ old('address') }}</x-textarea>

            <x-textarea name="notes" :label="__('common.labels.description')" rows="2">{{ old('notes') }}</x-textarea>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
