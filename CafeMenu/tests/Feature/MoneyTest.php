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
}
