<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * The single place every view renders a date through. Uses the Jalali
 * (Shamsi) calendar when the app locale is Persian, Gregorian otherwise —
 * never call ->format() directly on a date in a view.
 */
class LocalizedDate
{
    public static function format(DateTimeInterface|string|null $date, string $format = 'Y/m/d H:i'): string
    {
        if ($date === null) {
            return '';
        }

        $date = $date instanceof DateTimeInterface ? $date : Carbon::parse($date);

        return app()->getLocale() === 'fa'
            ? Jalali::format($date, $format)
            : $date->format($format);
    }

    /**
     * Proxies to Carbon's own diffForHumans(), which already translates the
     * relative phrasing ("5 minutes ago" -> "5 دقیقه پیش") once Carbon's
     * locale is set — see SetLocale middleware. Kept alongside format()
     * here so views have one consistent place to render any date from.
     */
    public static function diffForHumans(DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '';
        }

        $date = $date instanceof DateTimeInterface ? $date : Carbon::parse($date);

        return $date->diffForHumans();
    }
}
