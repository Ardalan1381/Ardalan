@extends('layouts.admin')

@section('admin-content')
    <a href="{{ route('admin.tables.index') }}" class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.table_sessions.back_to_tables') }}</a>

    <h1 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.table_sessions.title', ['table' => $table->name]) }}</h1>

    @if ($errors->any())
        <x-alert type="danger" class="mt-4">{{ $errors->first() }}</x-alert>
    @endif

    @php $current = $table->sessions->firstWhere('status', 'open'); @endphp

    @if ($current)
        <x-card class="mt-4">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('admin.table_sessions.open_since', ['date' => \App\Support\LocalizedDate::format($current->opened_at)]) }}</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.table_sessions.order_count', ['count' => $current->orders->count()]) }}</p>

            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.table-sessions.close', $current) }}" data-confirm="{{ __('admin.table_sessions.confirm_close') }}">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit" size="sm">{{ __('admin.table_sessions.close_session') }}</x-button>
                </form>

                <form method="POST" action="{{ route('admin.table-sessions.transfer', $current) }}" class="flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <select name="table_id" required class="rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                        <option value="">{{ __('admin.table_sessions.transfer_to') }}</option>
                        @foreach (\App\Models\Table::where('id', '!=', $table->id)->orderBy('name')->get() as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <x-button variant="secondary" type="submit" size="sm">{{ __('admin.table_sessions.transfer') }}</x-button>
                </form>

                @if ($openTableSessions->isNotEmpty())
                    <form method="POST" action="{{ route('admin.table-sessions.merge', $current) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="destination_session_id" required class="rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                            <option value="">{{ __('admin.table_sessions.merge_into') }}</option>
                            @foreach ($openTableSessions as $other)
                                <option value="{{ $other->id }}">{{ $other->table->name }}</option>
                            @endforeach
                        </select>
                        <x-button variant="secondary" type="submit" size="sm">{{ __('admin.table_sessions.merge') }}</x-button>
                    </form>
                @endif
            </div>
        </x-card>
    @else
        <form method="POST" action="{{ route('admin.tables.session.open', $table) }}" class="mt-4">
            @csrf
            <x-button type="submit">{{ __('admin.table_sessions.open_session') }}</x-button>
        </form>
    @endif

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.table_sessions.recent_sessions') }}</h2>

        <x-table class="mt-2" :headers="[__('admin.table_sessions.opened'), __('admin.table_sessions.closed'), __('admin.fields.status'), __('admin.table_sessions.orders')]">
            @foreach ($table->sessions as $session)
                <tr>
                    <td class="px-4 py-3">{{ \App\Support\LocalizedDate::format($session->opened_at) }}</td>
                    <td class="px-4 py-3">{{ $session->closed_at ? \App\Support\LocalizedDate::format($session->closed_at) : '—' }}</td>
                    <td class="px-4 py-3"><x-badge :status="$session->status">{{ $session->status === 'open' ? __('admin.table_sessions.status_open') : __('admin.table_sessions.status_closed') }}</x-badge></td>
                    <td class="px-4 py-3">{{ $session->orders->count() }}</td>
                </tr>
            @endforeach
        </x-table>
    </div>
@endsection
