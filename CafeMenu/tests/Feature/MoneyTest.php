<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_using_business_currency_and_decimal_places(): void
    {
        BusinessSetting::current()->update([
            'currency' => 'USD',
            'currency_minor_units' => 2,
        ]);

        $this->assertSame('45.00 USD', Money::format(4500));
    }

    public function test_formats_zero_decimal_currencies_without_decimals(): void
    {
        BusinessSetting::current()->update([
            'currency' => 'IRR',
            'currency_minor_units' => 0,
        ]);

        $this->assertSame('45,000 IRR', Money::format(45000));
    }

    public function test_formatting_is_integer_exact_for_large_and_negative_amounts(): void
    {
        BusinessSetting::current()->update(['currency' => 'USD', 'currency_minor_units' => 2]);

        $this->assertSame('90,071,992,547,409.93 USD', Money::format(9007199254740993));
        $this->assertSame('-12.05 USD', Money::format(-1205));
        $this->assertSame('0.07 USD', Money::format(7));
    }
}
