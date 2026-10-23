@extends('layouts.admin')

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.printers.new') }}</h1>

    <x-card class="mt-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.printers.store') }}" class="space-y-4">
            @csrf

            <x-input name="name" :label="__('admin.fields.name')" value="{{ old('name') }}" required />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select name="connection_type" :label="__('admin.printers.connection')" required>
                    @foreach (['network', 'usb'] as $type)
                        <option value="{{ $type }}" @selected(old('connection_type') === $type)>{{ __('admin.printers.connection_labels.'.$type) }}</option>
                    @endforeach
                </x-select>

                <x-select name="paper_width" :label="__('admin.printers.paper_width')" required>
                    @foreach (['58mm', '80mm'] as $width)
                        <option value="{{ $width }}" @selected(old('paper_width', '80mm') === $width)>{{ $width }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="ip_address" :label="__('admin.printers.ip_address')" value="{{ old('ip_address') }}" :hint="__('admin.printers.ip_address_hint')" />
                <x-input name="port" type="number" :label="__('admin.printers.port')" value="{{ old('port', 9100) }}" />
            </div>

            <x-input name="device_path" :label="__('admin.printers.device_path')" value="{{ old('device_path') }}" :hint="__('admin.printers.device_path_hint')" />

            <x-input name="farsi_codepage_table" type="number" min="0" max="255" :label="__('admin.printers.farsi_codepage_table')" value="{{ old('farsi_codepage_table') }}" :hint="__('admin.printers.farsi_codepage_table_hint')" />

            <x-checkbox name="is_active" :label="__('common.labels.active')" value="1" :checked="old('is_active', true)" />

            <x-button type="submit">{{ __('common.actions.create') }}</x-button>
        </form>
    </x-card>
@endsection
