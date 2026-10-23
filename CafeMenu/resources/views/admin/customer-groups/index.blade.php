@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.customer_groups.title') }}</h1>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.customers.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.customers') }}</a>
            @can('create', \App\Models\CustomerGroup::class)
                <x-button href="{{ route('admin.customer-groups.create') }}">{{ __('admin.customer_groups.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.customer_groups.customers_count'), __('admin.fields.actions')]">
        @forelse ($customerGroups as $group)
            <tr>
                <td class="px-4 py-3">{{ $group->name }}</td>
                <td class="px-4 py-3">{{ $group->customers_count }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $group)
                            <a href="{{ route('admin.customer-groups.edit', $group) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $group)
                            <form method="POST" action="{{ route('admin.customer-groups.destroy', $group) }}" data-confirm="{{ __('admin.customer_groups.confirm_delete') }}">
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
                <td colspan="3" class="px-4 py-12">
                    <x-empty-state :title="__('admin.customer_groups.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
