@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }}</h1>

    <x-card class="mt-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.print-routes.update', $printRoute) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-select name="document_type" :label="__('admin.fields.type')" required>
                @foreach (['receipt', 'kitchen_docket', 'bar_docket', 'invoice'] as $type)
                    <option value="{{ $type }}" @selected(old('document_type', $printRoute->document_type) === $type)>{{ __('admin.print_routes.document_type_labels.'.$type) }}</option>
                @endforeach
            </x-select>

            <x-select name="printer_id" :label="__('admin.printers.title')" required>
                @foreach ($printers as $printer)
                    <option value="{{ $printer->id }}" @selected(old('printer_id', $printRoute->printer_id) == $printer->id)>{{ $printer->name }}</option>
                @endforeach
            </x-select>

            <x-select name="category_id" :label="__('admin.print_routes.category')" :hint="__('admin.print_routes.category_hint')">
                <option value="">{{ __('admin.print_routes.any_category') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $printRoute->category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select>

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $printRoute->is_active)" />

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
