<?php

namespace App\Http\Requests\Admin;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $displayCurrencies = null;

        if (is_array($this->input('display_currencies'))) {
            $displayCurrencies = collect($this->input('display_currencies'))
                ->map(fn (array $entry, string $locale) => [
                    'is_active' => $this->boolean("display_currencies.{$locale}.is_active"),
                    'code' => $entry['code'] ?? null,
                    'symbol' => $entry['symbol'] ?? null,
                    'minor_units' => isset($entry['minor_units']) && $entry['minor_units'] !== '' ? (int) $entry['minor_units'] : null,
                ])
                ->all();
        }

        $workingHours = null;

        if (is_array($this->input('working_hours'))) {
            $workingHours = collect($this->input('working_hours'))
                ->map(fn (array $day, int $index) => [
                    ...$day,
                    'is_closed' => $this->boolean("working_hours.{$index}.is_closed"),
                ])
                ->values()
                ->all();
        }

        $this->merge([
            'tax_enabled' => $this->boolean('tax_enabled'),
            'service_charge_enabled' => $this->boolean('service_charge_enabled'),
            'accepts_dine_in' => $this->boolean('accepts_dine_in'),
            'accepts_takeaway' => $this->boolean('accepts_takeaway'),
            'accepts_pickup' => $this->boolean('accepts_pickup'),
            'accepts_delivery' => $this->boolean('accepts_delivery'),
            'accepts_cash_on_pickup' => $this->boolean('accepts_cash_on_pickup'),
            'ordering_enabled' => $this->boolean('ordering_enabled'),
            'display_currencies' => $displayCurrencies,
            'working_hours' => $workingHours,
            // Checkboxes that are all unchecked send no key at all — default
            // to [] so unchecking every gateway actually clears the column
            // instead of leaving the previous value untouched.
            'enabled_online_gateways' => $this->input('enabled_online_gateways', []),
            'gateway_credentials' => [
                'zarinpal' => [
                    'merchant_id' => $this->input('gateway_credentials.zarinpal.merchant_id'),
                    'sandbox' => $this->boolean('gateway_credentials.zarinpal.sandbox'),
                ],
                'zibal' => [
                    'merchant' => $this->input('gateway_credentials.zibal.merchant'),
                ],
                'mellat' => [
                    'terminal_id' => $this->input('gateway_credentials.mellat.terminal_id'),
                    'username' => $this->input('gateway_credentials.mellat.username'),
                    'password' => $this->input('gateway_credentials.mellat.password'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'max:10'],
            'currency_minor_units' => ['required', 'integer', 'between:0,4'],
            'locale' => ['required', Rule::in(['en', 'fa', 'ar'])],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'tax_enabled' => ['boolean'],
            'tax_rate_bps' => ['nullable', 'integer', 'between:0,10000'],
            'service_charge_enabled' => ['boolean'],
            'service_charge_rate_bps' => ['nullable', 'integer', 'between:0,10000'],
            'theme_color_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accepts_dine_in' => ['boolean'],
            'accepts_takeaway' => ['boolean'],
            'accepts_pickup' => ['boolean'],
            'accepts_delivery' => ['boolean'],
            'accepts_cash_on_pickup' => ['boolean'],
            'ordering_enabled' => ['boolean'],
            'display_currencies' => ['nullable', 'array'],
            'display_currencies.*.is_active' => ['boolean'],
            'display_currencies.*.code' => ['nullable', 'string', 'max:10'],
            'display_currencies.*.symbol' => ['nullable', 'string', 'max:10'],
            'display_currencies.*.minor_units' => ['nullable', 'integer', 'between:0,4'],
            'enabled_online_gateways' => ['nullable', 'array'],
            'enabled_online_gateways.*' => [Rule::in(['zarinpal', 'zibal', 'mellat'])],
            'gateway_credentials.zarinpal.merchant_id' => ['nullable', 'string', 'max:255'],
            'gateway_credentials.zarinpal.sandbox' => ['boolean'],
            'gateway_credentials.zibal.merchant' => ['nullable', 'string', 'max:255'],
            'gateway_credentials.mellat.terminal_id' => ['nullable', 'string', 'max:255'],
            'gateway_credentials.mellat.username' => ['nullable', 'string', 'max:255'],
            'gateway_credentials.mellat.password' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'social_links.telegram' => ['nullable', 'url', 'max:255'],
            'social_links.whatsapp' => ['nullable', 'url', 'max:255'],
            'social_links.website' => ['nullable', 'url', 'max:255'],
            'working_hours' => ['nullable', 'array', 'size:7'],
            'working_hours.*.day' => ['required', 'integer', 'between:0,6'],
            'working_hours.*.is_closed' => ['boolean'],
            'working_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'working_hours.*.closes_at' => ['nullable', 'date_format:H:i', 'after:working_hours.*.opens_at'],
        ];
    }
}
