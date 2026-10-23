@extends('layouts.install')

@section('title', 'Business')

@section('content')
    <h2 class="text-base font-semibold text-gray-900">Business details</h2>
    <p class="mt-1 text-sm text-gray-500">You can change all of this later in Business Settings.</p>

    <form method="POST" action="{{ route('install.business.store') }}" class="mt-4 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700">Business name</label>
            <input type="text" name="name" value="{{ old('name', config('app.name')) }}" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Currency code</label>
                <input type="text" name="currency" value="{{ old('currency', 'USD') }}" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Decimal places</label>
                <input type="number" name="currency_minor_units" value="{{ old('currency_minor_units', 2) }}" min="0" max="4" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Language</label>
                <select name="locale" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                    <option value="en" @selected(old('locale') === 'en')>English</option>
                    <option value="fa" @selected(old('locale') === 'fa')>Persian</option>
                    <option value="ar" @selected(old('locale') === 'ar')>Arabic</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Timezone</label>
                <input type="text" name="timezone" value="{{ old('timezone', 'UTC') }}" required list="timezones" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                <datalist id="timezones">
                    @foreach (DateTimeZone::listIdentifiers() as $tz)
                        <option value="{{ $tz }}"></option>
                    @endforeach
                </datalist>
            </div>
        </div>

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
            Save &amp; continue
        </button>
    </form>
@endsection
