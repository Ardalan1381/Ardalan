<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Contracts\PaymentInitiationResult;
use App\Services\Payments\Contracts\PaymentVerificationResult;
use App\Services\Payments\Support\GatewayCredentials;
use App\Services\Payments\Support\IranianAmount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Zibal REST v1 API. Docs: https://help.zibal.ir/IPG/API/
 */
class ZibalGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'zibal';
    }

    public function isConfigured(): bool
    {
        return filled(GatewayCredentials::get('zibal', 'merchant'));
    }

    public function initiate(Payment $payment, string $callbackUrl): PaymentInitiationResult
    {
        $response = Http::asJson()->post('https://gateway.zibal.ir/v1/request', [
            'merchant' => GatewayCredentials::get('zibal', 'merchant'),
            'amount' => IranianAmount::fromPaymentAmount($payment->amount),
            'callbackUrl' => $callbackUrl,
            'description' => "Order #{$payment->order->order_number}",
            'orderId' => $payment->order->order_number,
        ]);

        $data = $response->json() ?? [];

        if ($response->failed() || (int) ($data['result'] ?? 0) !== 100) {
            throw new PaymentGatewayException('Zibal payment request failed: '.$response->body());
        }

        $trackId = (string) $data['trackId'];

        $payment->gateway_token = $trackId;
        $payment->save();

        return PaymentInitiationResult::redirect('https://gateway.zibal.ir/start/'.$trackId);
    }

    public function verify(Request $request, Payment $payment): PaymentVerificationResult
    {
        if ($request->query('success') !== '1') {
            return PaymentVerificationResult::failure('Payment was not completed at the gateway.', $request->query());
        }

        $response = Http::asJson()->post('https://gateway.zibal.ir/v1/verify', [
            'merchant' => GatewayCredentials::get('zibal', 'merchant'),
            'trackId' => $payment->gateway_token,
        ]);

        $data = $response->json() ?? [];
        $result = (int) ($data['result'] ?? 0);

        // 100 = verified now, 201 = already verified (Zibal's own distinct
        // "already verified" code — do not conflate with ZarinPal's 101).
        if (in_array($result, [100, 201], true)) {
            return PaymentVerificationResult::success((string) ($data['refNumber'] ?? ''), $data);
        }

        return PaymentVerificationResult::failure('Zibal verification failed (result '.$result.').', $data);
    }
}
