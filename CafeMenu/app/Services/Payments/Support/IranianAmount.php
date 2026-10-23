<?php

namespace App\Services\Payments\Support;

use App\Models\BusinessSetting;

/**
 * The single place that converts a Payment.amount (stored in the
 * business's own currency minor units) into whatever unit the configured
 * Iranian gateway expects. Toman/Rial confusion is the single most common
 * bug in these integrations, so every gateway class must go through this
 * rather than doing its own multiplication.
 */
class IranianAmount
{
    public static function fromPaymentAmount(int $amount): int
    {
        $settings = BusinessSetting::current();

        // Guarded by PaymentGatewayManager::availableForCustomers() requiring
        // currency_minor_units === 0 before any Iranian gateway is offered,
        // so this is always a whole-number amount already.
        $businessAmount = (int) ($amount / (10 ** $settings->currency_minor_units));

        return config('payment.default_amount_unit') === 'rial'
            ? $businessAmount * 10
            : $businessAmount;
    }
}
