@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('common.actions.edit') }} — {{ $template->name }}</h1>

    <x-card class="mt-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.invoice-templates.update', $template) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name', $template->name) }}" required />

            <x-select name="paper_size" :label="__('admin.invoice_templates.paper_size')" required>
                @foreach (['a4', 'thermal_58mm', 'thermal_80mm'] as $size)
                    <option value="{{ $size }}" @selected(old('paper_size', $template->paper_size) === $size)>{{ __('admin.invoice_templates.paper_size_labels.'.$size) }}</option>
                @endforeach
            </x-select>

            <x-textarea name="header_html" :label="__('admin.invoice_templates.header_html')" :hint="__('admin.invoice_templates.header_html_hint')" rows="3">{{ old('header_html', $template->header_html) }}</x-textarea>

            <x-textarea name="footer_html" :label="__('admin.invoice_templates.footer_html')" :hint="__('admin.invoice_templates.footer_html_hint')" rows="3">{{ old('footer_html', $template->footer_html) }}</x-textarea>

            <div class="flex flex-wrap gap-6">
                <x-checkbox name="show_logo" :label="__('admin.invoice_templates.show_logo')" value="1" :checked="old('show_logo', $template->show_logo)" />
                <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', $template->is_active)" />
            </div>

            <x-button type="submit">{{ __('common.actions.save_changes') }}</x-button>
        </form>
    </x-card>
@endsection
