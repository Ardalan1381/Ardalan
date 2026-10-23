@extends('layouts.admin')

@section('admin-content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('nav.admin.users') }}</h1>
        @can('create', \App\Models\User::class)
            <x-button href="{{ route('admin.users.create') }}">{{ __('admin.users.new') }}</x-button>
        @endcan
    </div>

    <x-table class="mt-6" :headers="[__('admin.fields.name'), __('admin.fields.email'), __('admin.fields.role'), __('admin.fields.status'), __('admin.fields.actions')]">
        @forelse ($users as $user)
            <tr>
                <td class="px-4 py-3">{{ $user->name }}</td>
                <td class="px-4 py-3">{{ $user->email }}</td>
                <td class="px-4 py-3">{{ $user->role?->label ?? '—' }}</td>
                <td class="px-4 py-3"><x-badge :status="$user->is_active ? 'active' : 'inactive'">{{ $user->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge></td>
                <td class="px-4 py-3">
                    <div class="flex gap-3">
                        @can('update', $user)
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                        @endcan
                        @can('deactivate', $user)
                            <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-gray-600 dark:text-gray-400 hover:underline">
                                    {{ $user->is_active ? __('admin.users.deactivate') : __('admin.users.activate') }}
                                </button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-12">
                    <x-empty-state :title="__('admin.users.new')" />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
