@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.shifts.title') }}</h1>

    @if ($currentShift)
        <x-card class="mt-6 max-w-lg" :title="__('admin.shifts.current_shift')">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('admin.shifts.current_shift_open_since', ['time' => \App\Support\LocalizedDate::format($currentShift->opened_at)]) }}
                </p>
                <x-badge variant="success">{{ __('admin.shifts.status_open') }}</x-badge>
            </div>
            <x-button href="{{ route('admin.shifts.show', $currentShift) }}" class="mt-4" size="sm">{{ __('admin.shifts.view_current') }}</x-button>
        </x-card>
    @elseif (auth()->user()->hasPermission('shifts.view_own'))
        <x-card class="mt-6 max-w-lg" :title="__('admin.shifts.open_shift')">
            <form method="POST" action="{{ route('admin.shifts.store') }}" class="space-y-4">
                @csrf
                <x-input type="number" name="opening_cash_amount" min="0" step="1" :label="__('admin.shifts.opening_cash_amount')" value="{{ old('opening_cash_amount', 0) }}" required />
                <x-button type="submit">{{ __('admin.shifts.open_shift') }}</x-button>
            </form>
        </x-card>
    @endif

    <div class="mt-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.shifts.my_history') }}</h2>

        <x-table class="mt-2" :headers="[__('admin.shifts.opened_at'), __('admin.shifts.closed_at'), __('admin.fields.status'), __('admin.shifts.cash_difference'), __('admin.fields.actions')]">
            @forelse ($ownShifts as $shift)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap">{{ \App\Support\LocalizedDate::format($shift->opened_at) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $shift->closed_at ? \App\Support\LocalizedDate::format($shift->closed_at) : '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$shift->status === 'open' ? 'success' : 'neutral'">{{ __('admin.shifts.status_'.$shift->status) }}</x-badge>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if ($shift->cash_difference !== null)
                            <span class="{{ $shift->cash_difference == 0 ? 'text-gray-500 dark:text-gray-400' : ($shift->cash_difference > 0 ? 'text-success' : 'text-danger') }}">
                                {{ \App\Support\Money::format($shift->cash_difference) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.shifts.show', $shift) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.view') }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-12">
                        <x-empty-state :title="__('admin.shifts.empty')" />
                    </td>
                </tr>
            @endforelse
        </x-table>

        <div class="mt-4">
            {{ $ownShifts->links() }}
        </div>
    </div>

    @if ($allShifts)
        <div class="mt-10">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.shifts.all_staff_shifts') }}</h2>

            <x-table class="mt-2" :headers="[__('admin.shifts.staff_member'), __('admin.shifts.opened_at'), __('admin.shifts.closed_at'), __('admin.fields.status'), __('admin.shifts.cash_difference'), __('admin.fields.actions')]">
                @forelse ($allShifts as $shift)
                    <tr>
                        <td class="px-4 py-3">{{ $shift->user->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ \App\Support\LocalizedDate::format($shift->opened_at) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $shift->closed_at ? \App\Support\LocalizedDate::format($shift->closed_at) : '—' }}</td>
                        <td class="px-4 py-3">
                            <x-badge :variant="$shift->status === 'open' ? 'success' : 'neutral'">{{ __('admin.shifts.status_'.$shift->status) }}</x-badge>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($shift->cash_difference !== null)
                                <span class="{{ $shift->cash_difference == 0 ? 'text-gray-500 dark:text-gray-400' : ($shift->cash_difference > 0 ? 'text-success' : 'text-danger') }}">
                                    {{ \App\Support\Money::format($shift->cash_difference) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.shifts.show', $shift) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12">
                            <x-empty-state :title="__('admin.shifts.empty')" />
                        </td>
                    </tr>
                @endforelse
            </x-table>

            <div class="mt-4">
                {{ $allShifts->links() }}
            </div>
        </div>
    @endif
@endsection
