@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.invoice_templates.title') }}</h1>
        @can('create', \App\Models\InvoiceTemplate::class)
            <x-button href="{{ route('admin.invoice-templates.create') }}">{{ __('admin.invoice_templates.new') }}</x-button>
        @endcan
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.invoice_templates.paper_size'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($templates as $template)
            <tr>
                <td class="px-4 py-3">
                    {{ $template->name }}
                    @if ($template->is_default)
                        <x-badge variant="info" class="ms-2">{{ __('admin.invoice_templates.default') }}</x-badge>
                    @endif
                </td>
                <td class="px-4 py-3">{{ __('admin.invoice_templates.paper_size_labels.'.$template->paper_size) }}</td>
                <td class="px-4 py-3"><x-badge :status="$template->is_active ? 'active' : 'inactive'">{{ $template->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-3">
                        @can('update', $template)
                            <a href="{{ route('admin.invoice-templates.edit', $template) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                            @unless ($template->is_default)
                                <form method="POST" action="{{ route('admin.invoice-templates.make-default', $template) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.invoice_templates.make_default') }}</button>
                                </form>
                            @endunless
                        @endcan
                        @can('delete', $template)
                            <form method="POST" action="{{ route('admin.invoice-templates.destroy', $template) }}" data-confirm="{{ __('admin.invoice_templates.confirm_delete') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:underline">{{ __('common.actions.delete') }}</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-12">
                    <x-empty-state :title="__('admin.invoice_templates.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
