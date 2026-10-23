<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Pure-PHP Gregorian-to-Jalali (Persian/Shamsi) calendar conversion. No
 * ext-intl dependency — this app is self-hosted on operator-controlled
 * servers where that extension isn't guaranteed enabled, and the
 * conversion algorithm below is the same well-established one used across
 * the Persian PHP ecosystem for 20+ years. Verified against known
 * reference points (Nowruz 1403 = 2024-03-20, the 1979 revolution date
 * 22 Bahman 1357 = 1979-02-11) in JalaliTest.
 */
class Jalali
{
    /**
     * @var array<int, string>
     */
    private const MONTH_NAMES = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /**
     * @return array{0: int, 1: int, 2: int} [jalaliYear, jalaliMonth, jalaliDay]
     */
    public static function toJalali(DateTimeInterface $date): array
    {
        $gy = (int) $date->format('Y');
        $gm = (int) $date->format('n');
        $gd = (int) $date->format('j');

        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $jy = $gy <= 1600 ? 0 : 979;
        $gy -= $gy <= 1600 ? 621 : 1600;
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $gDaysInMonth[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /**
     * Formats a date using the Jalali calendar. Supports the small subset
     * of PHP date() tokens this app actually uses — not the full token
     * language.
     */
    public static function format(DateTimeInterface $date, string $format = 'Y/m/d H:i'): string
    {
        [$jy, $jm, $jd] = self::toJalali($date);

        return strtr($format, [
            'Y' => (string) $jy,
            'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
            'n' => (string) $jm,
            'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
            'j' => (string) $jd,
            'F' => self::MONTH_NAMES[$jm] ?? '',
            'H' => $date->format('H'),
            'i' => $date->format('i'),
            's' => $date->format('s'),
        ]);
    }
}
