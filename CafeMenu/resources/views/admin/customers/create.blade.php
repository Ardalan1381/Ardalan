@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.customers.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.customers.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-input name="phone" :label="__('admin.fields.phone')" value="{{ old('phone') }}" />

            <x-input name="email" type="email" :label="__('admin.fields.email')" value="{{ old('email') }}" />

            <x-select name="customer_group_id" :label="__('admin.fields.group')">
                <option value="">{{ __('admin.customers.no_group') }}</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" @selected(old('customer_group_id') == $group->id)>{{ $group->name }}</option>
                @endforeach
            </x-select>

            <x-textarea name="notes" :label="__('common.labels.description')" rows="3">{{ old('notes') }}</x-textarea>

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
