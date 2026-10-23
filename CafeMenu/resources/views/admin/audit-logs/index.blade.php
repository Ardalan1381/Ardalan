@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.audit_logs') }}</h1>

    <x-table class="mt-6" :headers="[__('admin.audit_logs.when'), __('admin.fields.user'), __('admin.fields.action'), __('admin.fields.subject'), __('admin.audit_logs.ip')]">
        @forelse ($logs as $log)
            <tr class="align-top">
                <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\LocalizedDate::format($log->created_at, 'Y/m/d H:i:s') }}</td>
                <td class="px-4 py-3">{{ $log->user?->name ?? __('admin.audit_logs.system_guest') }}</td>
                <td class="px-4 py-3">{{ $log->action }}</td>
                <td class="px-4 py-3">
                    @if ($log->auditable_type)
                        {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                    @endif
                    @if ($log->new_values)
                        <pre class="mt-1 max-w-xs overflow-x-auto text-xs text-gray-500 dark:text-gray-400">{{ json_encode($log->new_values) }}</pre>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-12">
                    <x-empty-state :title="__('admin.audit_logs.empty')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endsection
