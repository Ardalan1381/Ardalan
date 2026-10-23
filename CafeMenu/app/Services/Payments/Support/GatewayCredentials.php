<?php

namespace App\Services\Payments\Support;

use App\Models\BusinessSetting;

/**
 * The single place every gateway class reads its credentials from. The
 * business owner's admin-entered value (BusinessSetting, encrypted at rest)
 * always wins when present; a self-hoster who prefers env-based secrets
 * (e.g. injected via Docker/CI rather than typed into the admin UI) can
 * still set them in config/payment.php, which is used as the fallback.
 */
class GatewayCredentials
{
    public static function get(string $gateway, string $field): mixed
    {
        $stored = BusinessSetting::current()->gatewayCredential($gateway, $field);

        if ($stored !== null) {
            return $stored;
        }

        return config("payment.gateways.{$gateway}.{$field}");
    }
}
