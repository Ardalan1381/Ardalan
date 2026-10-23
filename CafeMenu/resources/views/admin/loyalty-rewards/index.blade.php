@extends('layouts.admin')

@section('admin-content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.loyalty_rewards.title') }}</h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('admin.loyalty-rules.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('nav.admin.loyalty') }}</a>
            @can('create', \App\Models\LoyaltyReward::class)
                <x-button href="{{ route('admin.loyalty-rewards.create') }}">{{ __('admin.loyalty_rewards.new') }}</x-button>
            @endcan
        </div>
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.loyalty_rewards.points_cost'), __('admin.loyalty_rewards.reward_type'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($loyaltyRewards as $loyaltyReward)
            <tr>
                <td class="px-4 py-3">{{ $loyaltyReward->name }}</td>
                <td class="px-4 py-3">{{ $loyaltyReward->points_cost }}</td>
                <td class="px-4 py-3">{{ __('admin.loyalty_rewards.reward_type_labels.'.$loyaltyReward->reward_type) }}</td>
                <td class="px-4 py-3"><x-badge :status="$loyaltyReward->is_active ? 'active' : 'inactive'">{{ $loyaltyReward->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $loyaltyReward)
                            <a href="{{ route('admin.loyalty-rewards.edit', $loyaltyReward) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('delete', $loyaltyReward)
                            <form method="POST" action="{{ route('admin.loyalty-rewards.destroy', $loyaltyReward) }}" data-confirm="{{ __('admin.loyalty_rewards.confirm_delete') }}">
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
                <td colspan="5" class="px-4 py-12">
                    <x-empty-state :title="__('admin.loyalty_rewards.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $loyaltyRewards->links() }}
    </div>
@endsection
