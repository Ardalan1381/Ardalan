<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * The config/route key for this gateway, e.g. "zarinpal".
     */
    public function key(): string;

    /**
     * Whether this gateway's required credentials are present in config.
     * Used to decide which gateways can even be offered to customers,
     * independent of whether the business owner has chosen to enable them.
     */
    public function isConfigured(): bool;

    /**
     * Start a payment: call the gateway's API/prepare its request, and
     * return where/how to send the customer next.
     */
    public function initiate(Payment $payment, string $callbackUrl): PaymentInitiationResult;

    /**
     * Verify a completed payment attempt server-side against the gateway.
     * Never trust the callback's own query/body parameters as proof of
     * payment — this always makes (or, for Mellat, completes) a network
     * call back to the gateway to confirm.
     */
    public function verify(Request $request, Payment $payment): PaymentVerificationResult;
}
