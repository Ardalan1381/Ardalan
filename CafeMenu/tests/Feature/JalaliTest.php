<?php

namespace Tests\Feature;

use App\Support\Jalali;
use App\Support\LocalizedDate;
use Carbon\Carbon;
use Tests\TestCase;

class JalaliTest extends TestCase
{
    public function test_converts_nowruz_1403(): void
    {
        $this->assertSame([1403, 1, 1], Jalali::toJalali(Carbon::create(2024, 3, 20)));
    }

    public function test_converts_the_1979_revolution_date(): void
    {
        $this->assertSame([1357, 11, 22], Jalali::toJalali(Carbon::create(1979, 2, 11)));
    }

    public function test_converts_nowruz_1405(): void
    {
        $this->assertSame([1405, 1, 1], Jalali::toJalali(Carbon::create(2026, 3, 21)));
    }

    public function test_format_produces_jalali_date_and_time(): void
    {
        $date = Carbon::create(2026, 8, 31, 14, 30, 0);

        $this->assertSame('1405/06/09 14:30', Jalali::format($date, 'Y/m/d H:i'));
    }

    public function test_format_supports_persian_month_name(): void
    {
        $date = Carbon::create(2026, 8, 31);

        $this->assertSame('9 شهریور 1405', Jalali::format($date, 'j F Y'));
    }

    public function test_localized_date_uses_jalali_when_locale_is_persian(): void
    {
        app()->setLocale('fa');

        $this->assertSame('1405/06/09 14:30', LocalizedDate::format(Carbon::create(2026, 8, 31, 14, 30)));
    }

    public function test_localized_date_uses_gregorian_when_locale_is_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('2026/08/31 14:30', LocalizedDate::format(Carbon::create(2026, 8, 31, 14, 30)));
    }

    public function test_localized_date_accepts_string_input(): void
    {
        app()->setLocale('fa');

        $this->assertSame('1405/06/09 00:00', LocalizedDate::format('2026-08-31 00:00:00'));
    }

    public function test_localized_date_returns_empty_string_for_null(): void
    {
        $this->assertSame('', LocalizedDate::format(null));
    }
}
