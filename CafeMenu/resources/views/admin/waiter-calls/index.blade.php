@extends('layouts.admin')

@section('admin-content')
    @vite('resources/js/admin-waiter-calls.js')

    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.waiter_calls_index.title') }}</h1>

    <x-table class="mt-6" :headers="[__('admin.fields.table'), __('admin.fields.status'), __('admin.waiter_calls_index.called'), __('admin.fields.actions')]">
        @forelse ($waiterCalls as $waiterCall)
            <tr>
                <td class="px-4 py-3">{{ $waiterCall->table->name }} · {{ $waiterCall->table->section->name }}</td>
                <td class="px-4 py-3" data-waiter-call-status-cell>
                    <x-badge status="{{ $waiterCall->status }}">{{ __('admin.waiter_calls_index.status.'.$waiterCall->status) }}</x-badge>
                    @if ($waiterCall->status === 'resolved' && $waiterCall->resolvedBy)
                        <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.waiter_calls_index.resolved_by', ['name' => $waiterCall->resolvedBy->name]) }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">{{ \App\Support\LocalizedDate::diffForHumans($waiterCall->created_at) }}</td>
                <td class="px-4 py-3" data-waiter-call-actions-cell>
                    @if ($waiterCall->status === 'pending')
                        <x-button
                            type="button"
                            variant="secondary"
                            size="sm"
                            data-resolve-waiter-call
                            data-url="{{ route('admin.waiter-calls.resolve', $waiterCall) }}"
                        >{{ __('admin.waiter_calls_index.resolve') }}</x-button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-12">
                    <x-empty-state :title="__('admin.waiter_calls_index.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $waiterCalls->links() }}
    </div>
@endsection
