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
 * ZarinPal REST v4 API. Docs: https://docs.zarinpal.com/
 *
 * Amount is sent explicitly as Toman ("IRT") rather than relying on the
 * API's default, since ZarinPal has changed that default between API
 * versions before.
 */
class ZarinpalGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'zarinpal';
    }

    public function isConfigured(): bool
    {
        return filled(GatewayCredentials::get('zarinpal', 'merchant_id'));
    }

    private function isSandbox(): bool
    {
        return (bool) GatewayCredentials::get('zarinpal', 'sandbox');
    }

    private function apiBaseUrl(): string
    {
        return $this->isSandbox() ? 'https://sandbox.zarinpal.com' : 'https://api.zarinpal.com';
    }

    private function payBaseUrl(): string
    {
        return $this->isSandbox() ? 'https://sandbox.zarinpal.com' : 'https://www.zarinpal.com';
    }

    public function initiate(Payment $payment, string $callbackUrl): PaymentInitiationResult
    {
        $params = [
            'merchant_id' => GatewayCredentials::get('zarinpal', 'merchant_id'),
            'amount' => IranianAmount::fromPaymentAmount($payment->amount),
            'currency' => 'IRT',
            'callback_url' => $callbackUrl,
            'description' => "Order #{$payment->order->order_number}",
        ];

        if (filled($payment->order->customer_phone)) {
            $params['mobile'] = $payment->order->customer_phone;
        }

        $response = Http::asJson()->post($this->apiBaseUrl().'/pg/v4/payment/request.json', $params);

        $data = $response->json('data') ?? [];

        if ($response->failed() || empty($data) || (int) ($data['code'] ?? 0) !== 100) {
            throw new PaymentGatewayException('ZarinPal payment request failed: '.$response->body());
        }

        $authority = $data['authority'];

        $payment->gateway_token = $authority;
        $payment->save();

        return PaymentInitiationResult::redirect($this->payBaseUrl().'/pg/StartPay/'.$authority);
    }

    public function verify(Request $request, Payment $payment): PaymentVerificationResult
    {
        if ($request->query('Status') !== 'OK') {
            return PaymentVerificationResult::failure('Payment was not completed at the gateway.', $request->query());
        }

        $response = Http::asJson()->post($this->apiBaseUrl().'/pg/v4/payment/verify.json', [
            'merchant_id' => GatewayCredentials::get('zarinpal', 'merchant_id'),
            'amount' => IranianAmount::fromPaymentAmount($payment->amount),
            'authority' => $payment->gateway_token,
        ]);

        $data = $response->json('data') ?? [];
        $code = (int) ($data['code'] ?? 0);

        // 100 = verified now, 101 = already verified (safe to treat as
        // success — this happens on duplicate callback delivery).
        if (in_array($code, [100, 101], true)) {
            return PaymentVerificationResult::success((string) ($data['ref_id'] ?? ''), $data);
        }

        return PaymentVerificationResult::failure('ZarinPal verification failed (code '.$code.').', $response->json() ?? []);
    }
}
