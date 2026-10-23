@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.discounts') }}</h1>
        <div class="flex items-center gap-4">
            @can('viewAny', \App\Models\Campaign::class)
                <a href="{{ route('admin.campaigns.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.discounts.manage_campaigns') }}</a>
            @endcan
            @can('create', \App\Models\Discount::class)
                <x-button href="{{ route('admin.discounts.create') }}">{{ __('admin.discounts.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.type'), __('admin.fields.status'), __('admin.fields.priority'), __('admin.fields.used_count'), __('admin.fields.actions')]">
        @forelse ($discounts as $discount)
            <tr>
                <td class="px-4 py-3">{{ $discount->name }}</td>
                <td class="px-4 py-3">{{ __('admin.discounts.type_labels.'.$discount->type) }}</td>
                <td class="px-4 py-3"><x-badge :status="$discount->is_active ? 'active' : 'inactive'">{{ $discount->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">{{ $discount->priority }}</td>
                <td class="px-4 py-3">{{ $discount->used_count }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $discount)
                            <a href="{{ route('admin.discounts.edit', $discount) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $discount)
                            <form method="POST" action="{{ route('admin.discounts.destroy', $discount) }}" data-confirm="{{ __('admin.discounts.confirm_delete') }}">
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
                <td colspan="6" class="px-4 py-12">
                    <x-empty-state :title="__('admin.discounts.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $discounts->links() }}
    </div>
@endsection
