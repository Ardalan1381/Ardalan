@extends('layouts.admin')

@section('admin-content')
    <a href="{{ route('admin.shifts.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.shifts.back_to_shifts') }}</a>

    <div class="mt-2 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $shift->user->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ \App\Support\LocalizedDate::format($shift->opened_at) }}</p>
        </div>
        <x-badge :variant="$shift->status === 'open' ? 'success' : 'neutral'">{{ __('admin.shifts.status_'.$shift->status) }}</x-badge>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card :title="__('admin.shifts.opening_cash_amount')">
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($shift->opening_cash_amount) }}</p>
        </x-card>
        <x-card :title="__('admin.shifts.running_total')">
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($shift->runningCashTotal()) }}</p>
        </x-card>
        @if ($shift->status === 'closed')
            <x-card :title="__('admin.shifts.cash_difference')">
                <p class="text-lg font-semibold {{ $shift->cash_difference == 0 ? 'text-gray-900 dark:text-gray-100' : ($shift->cash_difference > 0 ? 'text-success' : 'text-danger') }}">
                    {{ \App\Support\Money::format($shift->cash_difference) }}
                </p>
            </x-card>
        @endif
    </div>

    @if ($shift->status === 'closed')
        <x-card class="mt-4" :title="__('admin.shifts.closing_cash_amount')">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 text-sm">
                <div>
                    <p class="text-gray-500 dark:text-gray-400">{{ __('admin.shifts.closed_at') }}</p>
                    <p class="mt-1 text-gray-900 dark:text-gray-100">{{ \App\Support\LocalizedDate::format($shift->closed_at) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">{{ __('admin.shifts.expected_cash_amount') }}</p>
                    <p class="mt-1 text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($shift->expected_cash_amount) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">{{ __('admin.shifts.closing_cash_amount') }}</p>
                    <p class="mt-1 text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($shift->closing_cash_amount) }}</p>
                </div>
            </div>
            @if ($shift->notes)
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ $shift->notes }}</p>
            @endif
        </x-card>
    @endif

    <x-card class="mt-6" :title="__('admin.shifts.cash_movements')">
        <div class="-mx-5 overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-5 py-2 text-start font-medium">{{ __('admin.fields.date') }}</th>
                        <th class="px-2 py-2 text-start font-medium">{{ __('admin.shifts.movement_type') }}</th>
                        <th class="px-2 py-2 text-end font-medium">{{ __('admin.shifts.movement_amount') }}</th>
                        <th class="px-2 py-2 text-start font-medium">{{ __('admin.shifts.movement_reason') }}</th>
                        <th class="px-2 py-2 text-start font-medium">{{ __('admin.shifts.movement_reference') }}</th>
                        <th class="px-5 py-2 text-start font-medium">{{ __('admin.shifts.movement_recorded_by') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($shift->cashMovements as $movement)
                        <tr>
                            <td class="px-5 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ \App\Support\LocalizedDate::format($movement->created_at) }}</td>
                            <td class="px-2 py-2 whitespace-nowrap">{{ __('admin.shifts.movement_type_'.$movement->type) }}</td>
                            <td class="px-2 py-2 text-end whitespace-nowrap {{ $movement->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $movement->amount >= 0 ? '+' : '' }}{{ \App\Support\Money::format($movement->amount) }}
                            </td>
                            <td class="px-2 py-2 text-gray-600 dark:text-gray-400">{{ $movement->reason ?? '—' }}</td>
                            <td class="px-2 py-2 text-gray-500 dark:text-gray-400">{{ $movement->reference_type ? class_basename($movement->reference_type).' #'.$movement->reference_id : '—' }}</td>
                            <td class="px-5 py-2 whitespace-nowrap">{{ $movement->createdBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('admin.shifts.no_cash_movements') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('recordCashMovement', $shift)
            @if ($shift->status === 'open')
                <form method="POST" action="{{ route('admin.shifts.record-cash-movement', $shift) }}" class="mt-6 space-y-4 border-t border-gray-100 dark:border-gray-800 pt-6">
                    @csrf

                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.shifts.record_cash_movement') }}</p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select id="movement_type" name="type" :label="__('admin.shifts.movement_type')" required>
                            <option value="cash_in" @selected(old('type', 'cash_in') === 'cash_in')>{{ __('admin.shifts.movement_type_cash_in') }}</option>
                            <option value="cash_out" @selected(old('type') === 'cash_out')>{{ __('admin.shifts.movement_type_cash_out') }}</option>
                            <option value="adjustment" @selected(old('type') === 'adjustment')>{{ __('admin.shifts.movement_type_adjustment') }}</option>
                        </x-select>

                        <div id="movement_sign_wrapper" class="{{ old('type') === 'adjustment' ? '' : 'hidden' }}">
                            <x-select name="sign" :label="__('admin.shifts.movement_sign')">
                                <option value="+" @selected(old('sign', '+') === '+')>{{ __('admin.shifts.movement_sign_increase') }}</option>
                                <option value="-" @selected(old('sign') === '-')>{{ __('admin.shifts.movement_sign_decrease') }}</option>
                            </x-select>
                        </div>
                    </div>

                    <x-input type="number" name="amount" min="1" step="1" :label="__('admin.shifts.movement_amount')" value="{{ old('amount') }}" required />
                    <x-input name="reason" :label="__('admin.shifts.movement_reason')" :hint="__('admin.shifts.movement_reason_hint')" value="{{ old('reason') }}" required />

                    <x-button type="submit" size="sm">{{ __('admin.shifts.submit_movement') }}</x-button>
                </form>

                <script>
                    document.getElementById('movement_type')?.addEventListener('change', (event) => {
                        document.getElementById('movement_sign_wrapper')?.classList.toggle('hidden', event.target.value !== 'adjustment');
                    });
                </script>
            @endif
        @endcan
    </x-card>

    @can('close', $shift)
        @if ($shift->status === 'open')
            <x-card class="mt-6 max-w-lg" :title="__('admin.shifts.close_shift_confirm_title')">
                @if ($openUnpaidSessionsCount > 0)
                    <x-alert type="warning" class="mb-4">
                        {{ __('admin.shifts.open_unpaid_sessions_warning', ['count' => $openUnpaidSessionsCount]) }}
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('admin.shifts.close', $shift) }}" class="space-y-4" data-confirm="{{ __('admin.shifts.confirm_close') }}">
                    @csrf
                    @method('PATCH')

                    <x-input type="number" name="closing_cash_amount" min="0" step="1" :label="__('admin.shifts.closing_cash_amount')" value="{{ old('closing_cash_amount') }}" required />
                    <x-textarea name="notes" :label="__('admin.shifts.notes')" rows="2">{{ old('notes') }}</x-textarea>

                    <x-button type="submit" variant="danger">{{ __('admin.shifts.close_shift') }}</x-button>
                </form>
            </x-card>
        @endif
    @endcan
@endsection
