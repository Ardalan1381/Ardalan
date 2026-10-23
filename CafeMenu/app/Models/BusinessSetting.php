<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class BusinessSetting extends Model
{
    use HasMedia;

    /**
     * Mirrors the migration's column defaults. Needed because
     * Model::create()/firstOrCreate() populate the in-memory model only
     * with the attributes given plus the primary key — they do not refetch
     * DB-level column defaults — so without this, a freshly auto-created
     * settings row would read as null (falsy) for every unspecified column
     * until the next request re-fetched it from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'USD',
        'currency_minor_units' => 2,
        'locale' => 'en',
        'timezone' => 'UTC',
        'tax_enabled' => false,
        'tax_rate_bps' => 0,
        'service_charge_enabled' => false,
        'service_charge_rate_bps' => 0,
        'theme_color_primary' => '#111827',
        'theme_color_secondary' => '#6366F1',
        'accepts_dine_in' => true,
        'accepts_takeaway' => true,
        'accepts_pickup' => true,
        'accepts_delivery' => false,
        'accepts_cash_on_pickup' => true,
        'ordering_enabled' => true,
        'kitchen_delay_threshold_minutes' => 15,
    ];

    protected $fillable = [
        'name',
        'contact_email',
        'contact_phone',
        'address',
        'currency',
        'currency_minor_units',
        'locale',
        'timezone',
        'tax_enabled',
        'tax_rate_bps',
        'service_charge_enabled',
        'service_charge_rate_bps',
        'working_hours',
        'theme_color_primary',
        'theme_color_secondary',
        'social_links',
        'accepts_dine_in',
        'accepts_takeaway',
        'accepts_pickup',
        'accepts_delivery',
        'accepts_cash_on_pickup',
        'ordering_enabled',
        'display_currencies',
        'kitchen_delay_threshold_minutes',
        'enabled_online_gateways',
        // payment_gateway_credentials is deliberately NOT listed here —
        // like Payment::gateway_token, it must only ever be written through
        // mergeGatewayCredentials() (partial-merge, blank fields keep the
        // existing secret), never via a raw mass-assignment that could wipe
        // credentials whenever the settings form is saved without them.
    ];

    protected function casts(): array
    {
        return [
            'currency_minor_units' => 'integer',
            'tax_enabled' => 'boolean',
            'tax_rate_bps' => 'integer',
            'service_charge_enabled' => 'boolean',
            'service_charge_rate_bps' => 'integer',
            'working_hours' => 'array',
            'social_links' => 'array',
            'accepts_dine_in' => 'boolean',
            'accepts_takeaway' => 'boolean',
            'accepts_pickup' => 'boolean',
            'accepts_delivery' => 'boolean',
            'accepts_cash_on_pickup' => 'boolean',
            'ordering_enabled' => 'boolean',
            'display_currencies' => 'array',
            'enabled_online_gateways' => 'array',
            'payment_gateway_credentials' => 'encrypted:array',
        ];
    }

    /**
     * This is a single-business installation, so settings always live in one
     * row. Callers should use this instead of querying the model directly.
     */
    public static function current(): self
    {
        $existing = self::query()->find(1);

        if ($existing) {
            return $existing;
        }

        // 'id' is not fillable, so firstOrCreate(['id' => 1]) would silently
        // drop it and take the next AUTO_INCREMENT value — once that has
        // moved past 1 (a rolled-back insert on MySQL, a deleted row) every
        // call would create yet another row. Force the id explicitly, and
        // tolerate a concurrent request having inserted it first.
        try {
            $settings = new self(['name' => config('app.name')]);
            $settings->forceFill(['id' => 1])->save();

            return $settings;
        } catch (UniqueConstraintViolationException) {
            return self::query()->findOrFail(1);
        }
    }

    /**
     * Same as current(), but never throws. Used in layouts/bootstrapping
     * code that must render before the database is migrated (e.g. before
     * the installer has run), returning an unsaved default instance instead.
     */
    public static function safe(): self
    {
        try {
            return self::current();
        } catch (\Throwable) {
            return new self(['name' => config('app.name')]);
        }
    }

    public function logo(): ?Media
    {
        return $this->media->first();
    }

    /**
     * Resolves the currency to display for a given locale: the home
     * currency for the business's own locale (or when no override is
     * configured/active for a different one), otherwise the admin-entered
     * override from display_currencies. This never affects order/payment
     * totals — those always stay in the home currency.
     *
     * @return array{code: string, symbol: ?string, minor_units: int, is_home: bool}
     */
    public function currencyFor(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $override = $locale !== $this->locale ? ($this->display_currencies[$locale] ?? null) : null;

        if (is_array($override) && ($override['is_active'] ?? false)) {
            return [
                'code' => (string) ($override['code'] ?? $this->currency),
                'symbol' => $override['symbol'] ?? null,
                'minor_units' => (int) ($override['minor_units'] ?? $this->currency_minor_units),
                'is_home' => false,
            ];
        }

        return [
            'code' => $this->currency,
            'symbol' => null,
            'minor_units' => $this->currency_minor_units,
            'is_home' => true,
        ];
    }

    public function gatewayCredential(string $gateway, string $field): mixed
    {
        return data_get($this->payment_gateway_credentials, "{$gateway}.{$field}");
    }

    /**
     * Merges admin-submitted gateway credentials over whatever is already
     * stored. A blank string leaves the existing value untouched (the admin
     * form never re-displays a saved secret, so an empty field means
     * "unchanged", not "clear this") — booleans (e.g. sandbox toggles)
     * always take the submitted value since an unchecked box is meaningful.
     *
     * @param  array<string, array<string, mixed>>  $incoming
     */
    public function mergeGatewayCredentials(array $incoming): void
    {
        $current = $this->payment_gateway_credentials ?? [];

        foreach ($incoming as $gateway => $fields) {
            foreach ($fields as $field => $value) {
                if (is_bool($value) || filled($value)) {
                    $current[$gateway][$field] = $value;
                }
            }
        }

        // Direct assignment + save(), not update() — payment_gateway_credentials
        // is deliberately excluded from $fillable, so mass-assignment would
        // silently discard it.
        $this->payment_gateway_credentials = $current;
        $this->save();
    }
}
