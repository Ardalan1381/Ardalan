@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.campaigns.title') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('admin.discounts.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.discounts') }}</a>
            @can('create', \App\Models\Campaign::class)
                <x-button href="{{ route('admin.campaigns.create') }}">{{ __('admin.campaigns.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.campaigns.discounts_count'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($campaigns as $campaign)
            <tr>
                <td class="px-4 py-3">{{ $campaign->name }}</td>
                <td class="px-4 py-3">{{ $campaign->discounts_count }}</td>
                <td class="px-4 py-3"><x-badge :status="$campaign->is_active ? 'active' : 'inactive'">{{ $campaign->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $campaign)
                            <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $campaign)
                            <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign) }}" data-confirm="{{ __('admin.campaigns.confirm_delete') }}">
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
                    <x-empty-state :title="__('admin.campaigns.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
