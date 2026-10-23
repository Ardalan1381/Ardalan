<?php

namespace App\Support;

use App\Models\BusinessSetting;

/**
 * Formats integer minor-unit amounts for display using the business's
 * configured currency. Money is never stored or calculated as a float
 * anywhere in the app; this class only formats it for humans.
 */
class Money
{
    public static function format(int $minorUnits): string
    {
        $settings = BusinessSetting::current();
        $decimals = $settings->currency_minor_units;
        $amount = $minorUnits / (10 ** $decimals);

        return number_format($amount, $decimals).' '.$settings->currency;
    }

    /**
     * Same formatting, for a currency other than the business's home one —
     * used only for the optional per-language reference price on the menu
     * (see BusinessSetting::currencyFor() / Product::priceFor()). Never
     * used for anything that is actually charged: orders, payments and
     * reports always format through format() above, in the home currency.
     */
    public static function formatAs(int $minorUnits, string $currencyCode, int $decimals, ?string $symbol = null): string
    {
        $amount = $minorUnits / (10 ** $decimals);

        return number_format($amount, $decimals).' '.($symbol ?: $currencyCode);
    }
}
