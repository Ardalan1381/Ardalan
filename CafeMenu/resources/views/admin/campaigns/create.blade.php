@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.campaigns.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.campaigns.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-textarea name="description" :label="__('common.labels.description')" rows="3">{{ old('description') }}</x-textarea>

            <x-input name="starts_at" type="date" :label="__('admin.discounts.starts_at')" value="{{ old('starts_at') }}" />

            <x-input name="ends_at" type="date" :label="__('admin.discounts.ends_at')" value="{{ old('ends_at') }}" />

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
