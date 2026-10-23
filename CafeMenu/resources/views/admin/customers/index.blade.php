@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.customers') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            @can('viewAny', \App\Models\CustomerGroup::class)
                <a href="{{ route('admin.customer-groups.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.customers.manage_groups') }}</a>
            @endcan
            @can('create', \App\Models\Customer::class)
                <x-button href="{{ route('admin.customers.create') }}">{{ __('admin.customers.new') }}</x-button>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('admin.customers.index') }}" class="mt-4 max-w-sm">
        <x-input name="search" value="{{ $search }}" placeholder="{{ __('admin.customers.search_placeholder') }}" />
    </form>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.phone'), __('admin.fields.group'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($customers as $customer)
            <tr>
                <td class="px-4 py-3">{{ $customer->name }}</td>
                <td class="px-4 py-3">{{ $customer->phone ?? '—' }}</td>
                <td class="px-4 py-3">{{ $customer->group?->name ?? __('admin.customers.no_group') }}</td>
                <td class="px-4 py-3"><x-badge :status="$customer->status === 'active' ? 'active' : 'inactive'">{{ $customer->status === 'active' ? __('common.labels.active') : __('admin.customers.blocked') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $customer)
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('deactivate', $customer)
                            <form method="POST" action="{{ route('admin.customers.toggle-status', $customer) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-gray-600 dark:text-gray-400 hover:underline">
                                    {{ $customer->status === 'active' ? __('admin.customers.block') : __('admin.customers.unblock') }}
                                </button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-12">
                    <x-empty-state :title="__('admin.customers.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
@endsection
