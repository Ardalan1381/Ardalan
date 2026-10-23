@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.products.new') }}</h1>

    <x-card class="mt-6 max-w-md">
        <form method="POST" action="{{ route('admin.products.store') }}" class="space-y-4">
            @csrf

            <x-select name="category_id" :label="__('admin.fields.category')" required>
                <option value="">{{ __('admin.products.select_category') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select>

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <x-textarea name="description" :label="__('common.labels.description')" rows="3">{{ old('description') }}</x-textarea>

            <x-input
                name="base_price"
                type="number"
                :label="__('admin.products.base_price')"
                value="{{ old('base_price') }}"
                min="0"
                required
                :hint="__('admin.products.base_price_hint', ['currency' => \App\Models\BusinessSetting::current()->currency, 'example' => \App\Support\Money::format(1234)])"
            />

            <x-input name="discount_price" type="number" :label="__('admin.products.discount_price')" value="{{ old('discount_price') }}" min="0" />

            <x-select name="label" :label="__('admin.products.label')">
                <option value="">{{ __('admin.products.label_none') }}</option>
                <option value="new" @selected(old('label') === 'new')>{{ __('admin.products.label_new') }}</option>
                <option value="popular" @selected(old('label') === 'popular')>{{ __('admin.products.label_popular') }}</option>
                <option value="special" @selected(old('label') === 'special')>{{ __('admin.products.label_special') }}</option>
            </x-select>

            <x-input name="sort_order" type="number" :label="__('admin.fields.sort_order')" value="{{ old('sort_order', 0) }}" min="0" />

            <x-checkbox name="is_available" :label="__('admin.fields.available')" value="1" :checked="old('is_available', true)" />

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
