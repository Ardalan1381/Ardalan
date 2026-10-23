<?php

namespace App\Services\Payments;

use App\Models\BusinessSetting;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\MellatGateway;
use App\Services\Payments\Gateways\ZarinpalGateway;
use App\Services\Payments\Gateways\ZibalGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * @var array<string, class-string<PaymentGatewayInterface>>
     */
    private const DRIVERS = [
        'zarinpal' => ZarinpalGateway::class,
        'zibal' => ZibalGateway::class,
        'mellat' => MellatGateway::class,
    ];

    public function driver(string $key): PaymentGatewayInterface
    {
        if (! isset(self::DRIVERS[$key])) {
            throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
        }

        return app(self::DRIVERS[$key]);
    }

    /**
     * Gateways whose required credentials are present in config, regardless
     * of whether the business owner has chosen to show them to customers.
     *
     * @return list<string>
     */
    public function configured(): array
    {
        return array_values(array_filter(
            array_keys(self::DRIVERS),
            fn (string $key) => $this->driver($key)->isConfigured(),
        ));
    }

    /**
     * Gateways to actually offer customers: configured AND enabled by the
     * business owner. Iranian gateways only make sense when the shop's
     * whole currency is a zero-decimal Toman/Rial setup — this app has no
     * FX conversion, so a non-Iranian currency configuration disables all
     * of them outright rather than risking a wrong-by-orders-of-magnitude
     * charge.
     *
     * Also hidden whenever the customer is browsing in a locale whose
     * display currency (see BusinessSetting::currencyFor()) isn't the home
     * one: the order itself is still priced and charged in the home
     * currency regardless of what the menu showed, so offering an online
     * gateway here — which would silently charge the home-currency amount
     * — would look like a bait-and-switch to someone who was just looking
     * at, say, dollar prices. Cash payment is unaffected by this check.
     *
     * @return list<string>
     */
    public function availableForCustomers(): array
    {
        $settings = BusinessSetting::current();

        if ($settings->currency_minor_units !== 0 || ! $settings->currencyFor()['is_home']) {
            return [];
        }

        $enabledByBusiness = $settings->enabled_online_gateways ?? [];

        return array_values(array_intersect($enabledByBusiness, $this->configured()));
    }
}
