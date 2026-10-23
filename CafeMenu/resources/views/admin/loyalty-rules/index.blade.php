@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.loyalty_rules.title') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            @can('viewAny', \App\Models\LoyaltyReward::class)
                <a href="{{ route('admin.loyalty-rewards.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.loyalty_rules.manage_rewards') }}</a>
            @endcan
            @can('create', \App\Models\LoyaltyRule::class)
                <x-button href="{{ route('admin.loyalty-rules.create') }}">{{ __('admin.loyalty_rules.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.type'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($loyaltyRules as $loyaltyRule)
            <tr>
                <td class="px-4 py-3">{{ $loyaltyRule->name }}</td>
                <td class="px-4 py-3">{{ __('admin.loyalty_rules.type_labels.'.$loyaltyRule->type) }}</td>
                <td class="px-4 py-3"><x-badge :status="$loyaltyRule->is_active ? 'active' : 'inactive'">{{ $loyaltyRule->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $loyaltyRule)
                            <a href="{{ route('admin.loyalty-rules.edit', $loyaltyRule) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $loyaltyRule)
                            <form method="POST" action="{{ route('admin.loyalty-rules.destroy', $loyaltyRule) }}" data-confirm="{{ __('admin.loyalty_rules.confirm_delete') }}">
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
                    <x-empty-state :title="__('admin.loyalty_rules.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $loyaltyRules->links() }}
    </div>
@endsection
