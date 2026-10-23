@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.categories') }}</h1>
        @can('create', \App\Models\Category::class)
            <x-button href="{{ route('admin.categories.create') }}">{{ __('admin.categories.new') }}</x-button>
        @endcan
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.slug'), __('admin.fields.status'), __('admin.fields.actions')]">
        @include('admin.categories._tree', ['categories' => $categories, 'parentId' => null, 'depth' => 0])
    </x-table>
@endsection
