@extends('layouts.admin')

@php
    $days = __('menu.days');
    $hours = collect($settings->working_hours ?? [])->keyBy('day');
@endphp

@section('admin-content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.title') }}</h1>

    <div class="mt-6 max-w-md">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.logo.title') }}</h2>

        @if ($settings->logo())
            <img src="{{ $settings->logo()->url() }}" alt="Logo" class="mt-3 h-24 w-24 rounded-lg border border-gray-200 dark:border-gray-700 object-contain">
            <form method="POST" action="{{ route('admin.settings.business.logo.destroy') }}" class="mt-2" data-confirm="{{ __('business_settings.logo.confirm_remove') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-danger hover:underline">{{ __('business_settings.logo.remove') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.settings.business.logo.store') }}" enctype="multipart/form-data" class="mt-3">
            @csrf
            <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
            <x-button type="submit" size="sm" class="ms-2">{{ __('business_settings.logo.upload') }}</x-button>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.settings.business.update') }}" class="mt-10 max-w-2xl space-y-8">
        @csrf
        @method('PUT')

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.identity') }}</h2>

            <x-input name="name" :label="__('business_settings.fields.business_name')" value="{{ old('name', $settings->name) }}" required />

            <div class="grid grid-cols-2 gap-4">
                <x-input name="contact_email" type="email" :label="__('business_settings.fields.contact_email')" value="{{ old('contact_email', $settings->contact_email) }}" />
                <x-input name="contact_phone" :label="__('business_settings.fields.contact_phone')" value="{{ old('contact_phone', $settings->contact_phone) }}" />
            </div>

            <x-textarea name="address" :label="__('business_settings.fields.address')" rows="2">{{ old('address', $settings->address) }}</x-textarea>
        </section>

        <section class="grid grid-cols-2 gap-4">
            <h2 class="col-span-2 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.locale_currency') }}</h2>

            <x-select name="locale" :label="__('business_settings.fields.language')">
                <option value="en" @selected(old('locale', $settings->locale) === 'en')>English</option>
                <option value="fa" @selected(old('locale', $settings->locale) === 'fa')>فارسی</option>
                <option value="ar" @selected(old('locale', $settings->locale) === 'ar')>العربية</option>
            </x-select>

            <div>
                <x-input name="timezone" :label="__('business_settings.fields.timezone')" value="{{ old('timezone', $settings->timezone) }}" required list="timezones" />
                <datalist id="timezones">
                    @foreach (DateTimeZone::listIdentifiers() as $tz)
                        <option value="{{ $tz }}"></option>
                    @endforeach
                </datalist>
            </div>

            <x-input name="currency" :label="__('business_settings.fields.currency_code')" value="{{ old('currency', $settings->currency) }}" required />

            <x-input name="currency_minor_units" type="number" :label="__('business_settings.fields.currency_decimals')" value="{{ old('currency_minor_units', $settings->currency_minor_units) }}" min="0" max="4" required />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.display_currencies') }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('business_settings.display_currencies.intro') }}</p>

            @php
                $allLocaleNames = ['en' => 'English', 'fa' => 'فارسی', 'ar' => 'العربية'];
                $displayCurrencies = collect($settings->display_currencies ?? []);
                $homeLocale = old('locale', $settings->locale);
            @endphp

            @foreach ($allLocaleNames as $code => $localeName)
                @continue($code === $homeLocale)
                @php $entry = $displayCurrencies->get($code, []); @endphp

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $localeName }}</h3>
                        <label class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                            <input type="checkbox" name="display_currencies[{{ $code }}][is_active]" value="1" @checked(old("display_currencies.$code.is_active", $entry['is_active'] ?? false))>
                            {{ __('business_settings.display_currencies.enable') }}
                        </label>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-3">
                        <x-input
                            name="display_currencies[{{ $code }}][code]"
                            :label="__('business_settings.display_currencies.code')"
                            value="{{ old("display_currencies.$code.code", $entry['code'] ?? '') }}"
                            placeholder="USD"
                        />
                        <x-input
                            name="display_currencies[{{ $code }}][symbol]"
                            :label="__('business_settings.display_currencies.symbol')"
                            value="{{ old("display_currencies.$code.symbol", $entry['symbol'] ?? '') }}"
                            placeholder="$"
                        />
                        <x-input
                            name="display_currencies[{{ $code }}][minor_units]"
                            type="number"
                            min="0"
                            max="4"
                            :label="__('business_settings.display_currencies.minor_units')"
                            value="{{ old("display_currencies.$code.minor_units", $entry['minor_units'] ?? 2) }}"
                        />
                    </div>
                </div>
            @endforeach
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.tax_service') }}</h2>

            <x-checkbox name="tax_enabled" :label="__('business_settings.fields.apply_tax')" value="1" :checked="old('tax_enabled', $settings->tax_enabled)" />

            <x-input
                name="tax_rate_bps"
                type="number"
                step="0.01"
                :label="__('business_settings.fields.tax_rate')"
                value="{{ old('tax_rate_bps', $settings->tax_rate_bps) }}"
                min="0"
                max="10000"
                class="w-40"
                :hint="__('business_settings.fields.tax_rate_hint')"
            />

            <x-checkbox name="service_charge_enabled" :label="__('business_settings.fields.apply_service_charge')" value="1" :checked="old('service_charge_enabled', $settings->service_charge_enabled)" />

            <x-input
                name="service_charge_rate_bps"
                type="number"
                :label="__('business_settings.fields.service_charge_rate')"
                value="{{ old('service_charge_rate_bps', $settings->service_charge_rate_bps) }}"
                min="0"
                max="10000"
                class="w-40"
            />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.ordering') }}</h2>

            <x-checkbox
                name="ordering_enabled"
                :label="__('business_settings.order_types.ordering_enabled')"
                :hint="__('business_settings.order_types.ordering_enabled_hint')"
                value="1"
                :checked="old('ordering_enabled', $settings->ordering_enabled)"
            />

            <x-checkbox name="accepts_dine_in" :label="__('business_settings.order_types.dine_in')" value="1" :checked="old('accepts_dine_in', $settings->accepts_dine_in)" />
            <x-checkbox name="accepts_takeaway" :label="__('business_settings.order_types.takeaway')" value="1" :checked="old('accepts_takeaway', $settings->accepts_takeaway)" />
            <x-checkbox name="accepts_pickup" :label="__('business_settings.order_types.pickup')" value="1" :checked="old('accepts_pickup', $settings->accepts_pickup)" />
            <x-checkbox name="accepts_delivery" :label="__('business_settings.order_types.delivery')" value="1" :checked="old('accepts_delivery', $settings->accepts_delivery)" />

            <x-checkbox
                name="accepts_cash_on_pickup"
                :label="__('business_settings.order_types.cash_on_pickup')"
                :hint="__('business_settings.order_types.cash_on_pickup_hint')"
                value="1"
                :checked="old('accepts_cash_on_pickup', $settings->accepts_cash_on_pickup)"
            />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.online_payments') }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('business_settings.online_payments.intro') }}</p>

            @if ($settings->currency_minor_units !== 0)
                <x-alert type="warning">
                    {{ __('business_settings.online_payments.currency_warning', ['currency' => $settings->currency, 'decimals' => $settings->currency_minor_units]) }}
                </x-alert>
            @endif

            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('business_settings.online_payments.choose_hint') }}</p>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('payments.gateways.zarinpal') }}</h3>
                        @if (in_array('zarinpal', $configuredGateways, true))
                            <x-checkbox
                                name="enabled_online_gateways[]"
                                value="zarinpal"
                                :label="__('business_settings.online_payments.enable_label')"
                                :checked="in_array('zarinpal', old('enabled_online_gateways', $settings->enabled_online_gateways ?? []), true)"
                            />
                        @endif
                    </div>

                    <div class="mt-3 space-y-3">
                        <x-input
                            name="gateway_credentials[zarinpal][merchant_id]"
                            :label="__('business_settings.online_payments.zarinpal.merchant_id')"
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            :hint="$settings->gatewayCredential('zarinpal', 'merchant_id') ? __('business_settings.online_payments.saved_hint') : null"
                        />
                        <x-checkbox
                            name="gateway_credentials[zarinpal][sandbox]"
                            value="1"
                            :label="__('business_settings.online_payments.zarinpal.sandbox')"
                            :checked="old('gateway_credentials.zarinpal.sandbox', $settings->gatewayCredential('zarinpal', 'sandbox'))"
                        />
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('payments.gateways.zibal') }}</h3>
                        @if (in_array('zibal', $configuredGateways, true))
                            <x-checkbox
                                name="enabled_online_gateways[]"
                                value="zibal"
                                :label="__('business_settings.online_payments.enable_label')"
                                :checked="in_array('zibal', old('enabled_online_gateways', $settings->enabled_online_gateways ?? []), true)"
                            />
                        @endif
                    </div>

                    <x-input
                        name="gateway_credentials[zibal][merchant]"
                        :label="__('business_settings.online_payments.zibal.merchant')"
                        :hint="$settings->gatewayCredential('zibal', 'merchant') ? __('business_settings.online_payments.saved_hint') : null"
                        class="mt-3"
                    />
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('payments.gateways.mellat') }}</h3>
                        @if (in_array('mellat', $configuredGateways, true))
                            <x-checkbox
                                name="enabled_online_gateways[]"
                                value="mellat"
                                :label="__('business_settings.online_payments.enable_label')"
                                :checked="in_array('mellat', old('enabled_online_gateways', $settings->enabled_online_gateways ?? []), true)"
                            />
                        @endif
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-4">
                        <x-input
                            name="gateway_credentials[mellat][terminal_id]"
                            :label="__('business_settings.online_payments.mellat.terminal_id')"
                            :hint="$settings->gatewayCredential('mellat', 'terminal_id') ? __('business_settings.online_payments.saved_hint') : null"
                        />
                        <x-input
                            name="gateway_credentials[mellat][username]"
                            :label="__('business_settings.online_payments.mellat.username')"
                            :hint="$settings->gatewayCredential('mellat', 'username') ? __('business_settings.online_payments.saved_hint') : null"
                        />
                        <x-input
                            name="gateway_credentials[mellat][password]"
                            type="password"
                            :label="__('business_settings.online_payments.mellat.password')"
                            :hint="$settings->gatewayCredential('mellat', 'password') ? __('business_settings.online_payments.saved_hint') : null"
                        />
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.theme') }}</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('business_settings.fields.primary_color') }}</label>
                    <input type="color" name="theme_color_primary" value="{{ old('theme_color_primary', $settings->theme_color_primary) }}" class="mt-1 h-10 w-full rounded-lg border-gray-300 dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('business_settings.fields.secondary_color') }}</label>
                    <input type="color" name="theme_color_secondary" value="{{ old('theme_color_secondary', $settings->theme_color_secondary) }}" class="mt-1 h-10 w-full rounded-lg border-gray-300 dark:border-gray-600">
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.social_links') }}</h2>

            @foreach (['instagram', 'telegram', 'whatsapp', 'website'] as $key)
                <x-input
                    name="social_links[{{ $key }}]"
                    type="url"
                    :label="__('business_settings.social.'.$key)"
                    value="{{ old("social_links.$key", data_get($settings->social_links, $key)) }}"
                />
            @endforeach
        </section>

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('business_settings.sections.working_hours') }}</h2>

            @foreach ($days as $day => $label)
                @php $entry = $hours->get($day, ['is_closed' => false, 'opens_at' => null, 'closes_at' => null]); @endphp
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <input type="hidden" name="working_hours[{{ $day }}][day]" value="{{ $day }}">
                    <span class="w-24">{{ $label }}</span>
                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                        <input type="checkbox" name="working_hours[{{ $day }}][is_closed]" value="1" @checked(old("working_hours.$day.is_closed", $entry['is_closed'] ?? false))>
                        {{ __('business_settings.fields.closed') }}
                    </label>
                    <input type="time" name="working_hours[{{ $day }}][opens_at]" value="{{ old("working_hours.$day.opens_at", $entry['opens_at'] ?? '') }}" class="rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                    <span>{{ __('business_settings.fields.to') }}</span>
                    <input type="time" name="working_hours[{{ $day }}][closes_at]" value="{{ old("working_hours.$day.closes_at", $entry['closes_at'] ?? '') }}" class="rounded-lg border-gray-300 dark:border-gray-600 text-sm shadow-sm">
                </div>
            @endforeach
        </section>

        <x-button type="submit">{{ __('business_settings.save') }}</x-button>
    </form>
@endsection
