@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $campaign->name }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $campaign->name) }}" required />

            <x-textarea name="description" :label="__('common.labels.description')" rows="3">{{ old('description', $campaign->description) }}</x-textarea>

            <x-input name="starts_at" type="date" :label="__('admin.discounts.starts_at')" value="{{ old('starts_at', $campaign->starts_at?->toDateString()) }}" />

            <x-input name="ends_at" type="date" :label="__('admin.discounts.ends_at')" value="{{ old('ends_at', $campaign->ends_at?->toDateString()) }}" />

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $campaign->is_active)" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
