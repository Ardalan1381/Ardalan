<?php

namespace App\Services\Payments\Contracts;

final class PaymentVerificationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $gatewayReference,
        public readonly ?string $errorMessage,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function success(?string $gatewayReference, array $raw = []): self
    {
        return new self(true, $gatewayReference, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failure(?string $errorMessage, array $raw = []): self
    {
        return new self(false, null, $errorMessage, $raw);
    }
}
