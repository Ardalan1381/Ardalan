<?php

namespace App\Services\Payments\Contracts;

/**
 * Where to send the customer to pay. ZarinPal/Zibal redirect with a
 * simple GET; Mellat requires an auto-submitting POST form. One shape
 * covers both so the controller never special-cases a gateway.
 */
final class PaymentInitiationResult
{
    /**
     * @param  array<string, string>  $fields
     */
    private function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $fields = [],
    ) {}

    public static function redirect(string $url): self
    {
        return new self('GET', $url);
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function autoSubmitForm(string $url, array $fields): self
    {
        return new self('POST', $url, $fields);
    }
}
