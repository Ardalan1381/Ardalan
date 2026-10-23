<?php

namespace Tests\Feature\Payments;

use App\Models\BusinessSetting;
use App\Services\Payments\Gateways\ZarinpalGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentGatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_resolves_known_gateway(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertInstanceOf(ZarinpalGateway::class, $manager->driver('zarinpal'));
    }

    public function test_driver_throws_for_unknown_gateway(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PaymentGatewayManager::class)->driver('unknown');
    }

    public function test_configured_only_lists_gateways_with_credentials(): void
    {
        config(['payment.gateways.zarinpal.merchant_id' => 'test-merchant']);
        config(['payment.gateways.zibal.merchant' => null]);
        config(['payment.gateways.mellat.terminal_id' => null]);

        $manager = app(PaymentGatewayManager::class);

        $this->assertSame(['zarinpal'], $manager->configured());
    }

    public function test_available_for_customers_is_empty_when_currency_has_decimals(): void
    {
        config(['payment.gateways.zarinpal.merchant_id' => 'test-merchant']);
        BusinessSetting::current()->update([
            'currency' => 'USD',
            'currency_minor_units' => 2,
            'enabled_online_gateways' => ['zarinpal'],
        ]);

        $manager = app(PaymentGatewayManager::class);

        $this->assertSame([], $manager->availableForCustomers());
    }

    public function test_available_for_customers_intersects_configured_and_enabled(): void
    {
        config(['payment.gateways.zarinpal.merchant_id' => 'test-merchant']);
        config(['payment.gateways.zibal.merchant' => null]);
        BusinessSetting::current()->update([
            'currency' => 'IRT',
            'currency_minor_units' => 0,
            'enabled_online_gateways' => ['zarinpal', 'zibal'],
        ]);

        $manager = app(PaymentGatewayManager::class);

        $this->assertSame(['zarinpal'], $manager->availableForCustomers());
    }

    public function test_available_for_customers_is_empty_when_business_has_not_enabled_any(): void
    {
        config(['payment.gateways.zarinpal.merchant_id' => 'test-merchant']);
        BusinessSetting::current()->update([
            'currency' => 'IRT',
            'currency_minor_units' => 0,
            'enabled_online_gateways' => null,
        ]);

        $manager = app(PaymentGatewayManager::class);

        $this->assertSame([], $manager->availableForCustomers());
    }
}
