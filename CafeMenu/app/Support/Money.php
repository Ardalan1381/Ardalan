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

        return self::formatMinorUnits($minorUnits, $settings->currency_minor_units).' '.$settings->currency;
    }

    /**
     * Integer-exact formatting: the whole and fractional parts are split
     * with integer arithmetic instead of dividing into a float, which can
     * round large amounts the wrong way (e.g. 90071992547409.93).
     */
    private static function formatMinorUnits(int $minorUnits, int $decimals): string
    {
        $sign = $minorUnits < 0 ? '-' : '';
        $absolute = abs($minorUnits);

        if ($decimals <= 0) {
            return $sign.number_format($absolute);
        }

        $factor = 10 ** $decimals;
        $whole = intdiv($absolute, $factor);
        $fraction = str_pad((string) ($absolute % $factor), $decimals, '0', STR_PAD_LEFT);

        return $sign.number_format($whole).'.'.$fraction;
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
        return self::formatMinorUnits($minorUnits, $decimals).' '.($symbol ?: $currencyCode);
    }
}
