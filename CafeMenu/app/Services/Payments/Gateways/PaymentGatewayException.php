<?php

namespace App\Services\Payments\Gateways;

use RuntimeException;

/**
 * Thrown when a gateway's own API call fails outright (network error,
 * malformed response, request-stage rejection) — distinct from a
 * PaymentVerificationResult::failure(), which represents a normal
 * "the payment didn't succeed" outcome the controller renders a status
 * page for. This is for the initiate() step, where there is no payment
 * outcome yet to report — only a hard failure to start one.
 */
class PaymentGatewayException extends RuntimeException {}
