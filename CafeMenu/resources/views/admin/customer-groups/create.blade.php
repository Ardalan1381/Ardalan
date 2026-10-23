@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.customer_groups.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.customer-groups.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-textarea name="description" :label="__('common.labels.description')" rows="3">{{ old('description') }}</x-textarea>

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
