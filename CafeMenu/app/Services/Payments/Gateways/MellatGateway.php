<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Contracts\PaymentInitiationResult;
use App\Services\Payments\Contracts\PaymentVerificationResult;
use App\Services\Payments\Support\GatewayCredentials;
use App\Services\Payments\Support\IranianAmount;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Behpardakht Mellat (Bank Mellat) — a SOAP 1.1 service, not REST like
 * ZarinPal/Zibal. ext-soap is not assumed to be installed (this app runs
 * self-hosted on operator-controlled servers), so requests are hand-built
 * XML envelopes posted via the Http client and parsed with DOMDocument —
 * no SoapClient, no new Composer dependency.
 *
 * WSDL: https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl
 * The exact element names/order below match Behpardakht's long-published
 * integration guide; Shaparak has been known to revise this WSDL, so
 * verify against the live WSDL before taking this to production.
 */
class MellatGateway implements PaymentGatewayInterface
{
    private const ENDPOINT = 'https://bpm.shaparak.ir/pgwchannel/services/pgw';

    private const NAMESPACE = 'http://interfaces.core.sw.bps.com/';

    public function key(): string
    {
        return 'mellat';
    }

    public function isConfigured(): bool
    {
        return filled(GatewayCredentials::get('mellat', 'terminal_id'))
            && filled(GatewayCredentials::get('mellat', 'username'))
            && filled(GatewayCredentials::get('mellat', 'password'));
    }

    public function initiate(Payment $payment, string $callbackUrl): PaymentInitiationResult
    {
        $orderId = $payment->id;
        $now = now();

        $result = $this->call('bpPayRequest', [
            'terminalId' => GatewayCredentials::get('mellat', 'terminal_id'),
            'userName' => GatewayCredentials::get('mellat', 'username'),
            'userPassword' => GatewayCredentials::get('mellat', 'password'),
            'orderId' => $orderId,
            'amount' => IranianAmount::fromPaymentAmount($payment->amount),
            'localDate' => $now->format('Ymd'),
            'localTime' => $now->format('His'),
            'additionalData' => '',
            'callBackUrl' => $callbackUrl,
            'payerId' => 0,
        ]);

        [$resultCode, $refId] = array_pad(explode(',', $result, 2), 2, null);

        if ($resultCode !== '0' || ! $refId) {
            throw new PaymentGatewayException("Mellat bpPayRequest failed (code {$resultCode}).");
        }

        $payment->gateway_token = $refId;
        $payment->save();

        return PaymentInitiationResult::autoSubmitForm(
            'https://bpm.shaparak.ir/pgwchannel/startpay.mellat',
            ['RefId' => $refId],
        );
    }

    public function verify(Request $request, Payment $payment): PaymentVerificationResult
    {
        if ($request->input('ResCode') !== '0') {
            return PaymentVerificationResult::failure(
                'Payment was not completed at the gateway (ResCode '.$request->input('ResCode').').',
                $request->all(),
            );
        }

        $orderId = $payment->id;
        $saleOrderId = $request->input('SaleOrderId', $orderId);
        $saleReferenceId = $request->input('SaleReferenceId');

        // The callback body is attacker-controllable. bpPayRequest was made
        // with orderId = this payment's id, so a SaleOrderId naming any
        // other sale (e.g. a cheaper one the attacker really did pay for)
        // must never be used to verify this payment.
        if ((string) $saleOrderId !== (string) $orderId || blank($saleReferenceId)) {
            return PaymentVerificationResult::failure('Mellat callback does not belong to this payment.', $request->all());
        }

        $verifyResult = $this->call('bpVerifyRequest', [
            'terminalId' => GatewayCredentials::get('mellat', 'terminal_id'),
            'userName' => GatewayCredentials::get('mellat', 'username'),
            'userPassword' => GatewayCredentials::get('mellat', 'password'),
            'orderId' => $orderId,
            'saleOrderId' => $saleOrderId,
            'saleReferenceId' => $saleReferenceId,
        ]);

        if ($verifyResult !== '0') {
            return PaymentVerificationResult::failure(
                'Mellat verification failed (code '.$verifyResult.').',
                $request->all(),
            );
        }

        $settleResult = $this->call('bpSettleRequest', [
            'terminalId' => GatewayCredentials::get('mellat', 'terminal_id'),
            'userName' => GatewayCredentials::get('mellat', 'username'),
            'userPassword' => GatewayCredentials::get('mellat', 'password'),
            'orderId' => $orderId,
            'saleOrderId' => $saleOrderId,
            'saleReferenceId' => $saleReferenceId,
        ]);

        if ($settleResult !== '0') {
            // Verified but not settled: funds are reserved at the bank but
            // not yet transferred. v1 does not auto-reverse (bpReversalRequest)
            // — this is logged for manual/ops follow-up rather than silently
            // treated as a normal failure, since money may already be held.
            Log::error('Mellat payment verified but settlement failed — needs manual reversal or retry.', [
                'payment_id' => $payment->id,
                'order_id' => $orderId,
                'sale_reference_id' => $saleReferenceId,
                'settle_result' => $settleResult,
            ]);

            return PaymentVerificationResult::failure(
                'Payment could not be finalized (settlement code '.$settleResult.').',
                $request->all(),
            );
        }

        return PaymentVerificationResult::success($saleReferenceId, $request->all());
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function call(string $method, array $params): string
    {
        $response = Http::withBody($this->buildEnvelope($method, $params), 'text/xml; charset=utf-8')
            ->withHeaders(['SOAPAction' => ''])
            ->post(self::ENDPOINT);

        if ($response->failed()) {
            throw new PaymentGatewayException("Mellat {$method} HTTP request failed: ".$response->status());
        }

        return $this->extractReturnValue($response->body());
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function buildEnvelope(string $method, array $params): string
    {
        $body = '';
        foreach ($params as $name => $value) {
            $body .= '<'.$name.'>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES).'</'.$name.'>';
        }

        $namespace = self::NAMESPACE;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:pgw="{$namespace}">
   <soapenv:Header/>
   <soapenv:Body>
      <pgw:{$method}>{$body}</pgw:{$method}>
   </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function extractReturnValue(string $xml): string
    {
        $document = new DOMDocument;

        if (! @$document->loadXML($xml)) {
            throw new PaymentGatewayException('Mellat response was not valid XML.');
        }

        $nodes = $document->getElementsByTagName('return');

        if ($nodes->length === 0) {
            throw new PaymentGatewayException('Mellat response did not contain a <return> value.');
        }

        return trim($nodes->item(0)->textContent);
    }
}
