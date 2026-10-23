@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $supplier->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $supplier->name) }}" required />

            <x-input name="contact_name" :label="__('admin.suppliers.contact_name')" value="{{ old('contact_name', $supplier->contact_name) }}" />

            <x-input name="phone" :label="__('admin.fields.phone')" value="{{ old('phone', $supplier->phone) }}" />

            <x-input name="email" type="email" :label="__('admin.fields.email')" value="{{ old('email', $supplier->email) }}" />

            <x-textarea name="address" :label="__('admin.suppliers.address')" rows="2">{{ old('address', $supplier->address) }}</x-textarea>

            <x-textarea name="notes" :label="__('common.labels.description')" rows="2">{{ old('notes', $supplier->notes) }}</x-textarea>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $supplier->is_active)" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
