<?php

namespace Tests\Feature\Payments;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\Payments\Contracts\PaymentInitiationResult;
use App\Services\Payments\Gateways\PaymentGatewayException;
use App\Services\Payments\Gateways\ZarinpalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZarinpalGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BusinessSetting::current()->update(['currency' => 'IRT', 'currency_minor_units' => 0]);
        config(['payment.gateways.zarinpal.merchant_id' => 'test-merchant']);
    }

    private function placeOrder(int $basePrice = 50000): Order
    {
        $product = Product::factory()->create(['base_price' => $basePrice]);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_initiate_stores_authority_and_returns_redirect(): void
    {
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A00000000000000000000000000012345'],
                'errors' => [],
            ]),
        ]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);

        $result = (new ZarinpalGateway)->initiate($payment, 'https://example.test/callback');

        $this->assertInstanceOf(PaymentInitiationResult::class, $result);
        $this->assertSame('GET', $result->method);
        $this->assertStringContainsString('A00000000000000000000000000012345', $result->url);
        $this->assertSame('A00000000000000000000000000012345', $payment->fresh()->gateway_token);
    }

    public function test_initiate_throws_on_gateway_error(): void
    {
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::response(['data' => [], 'errors' => ['message' => 'bad merchant']], 200),
        ]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);

        $this->expectException(PaymentGatewayException::class);

        (new ZarinpalGateway)->initiate($payment, 'https://example.test/callback');
    }

    public function test_verify_fails_fast_on_nok_status_without_calling_gateway(): void
    {
        Http::fake();

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = 'auth-123';
        $payment->save();

        $result = (new ZarinpalGateway)->verify(Request::create('/', 'GET', ['Authority' => 'auth-123', 'Status' => 'NOK']), $payment);

        $this->assertFalse($result->successful);
        Http::assertNothingSent();
    }

    public function test_verify_succeeds_on_code_100(): void
    {
        Http::fake([
            '*/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 987654], 'errors' => []]),
        ]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = 'auth-123';
        $payment->save();

        $result = (new ZarinpalGateway)->verify(Request::create('/', 'GET', ['Authority' => 'auth-123', 'Status' => 'OK']), $payment);

        $this->assertTrue($result->successful);
        $this->assertSame('987654', $result->gatewayReference);
    }

    public function test_verify_treats_already_verified_code_101_as_success(): void
    {
        Http::fake([
            '*/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 101, 'ref_id' => 987654], 'errors' => []]),
        ]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = 'auth-123';
        $payment->save();

        $result = (new ZarinpalGateway)->verify(Request::create('/', 'GET', ['Authority' => 'auth-123', 'Status' => 'OK']), $payment);

        $this->assertTrue($result->successful);
    }

    public function test_verify_fails_on_unknown_code(): void
    {
        Http::fake([
            '*/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 102], 'errors' => []]),
        ]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = 'auth-123';
        $payment->save();

        $result = (new ZarinpalGateway)->verify(Request::create('/', 'GET', ['Authority' => 'auth-123', 'Status' => 'OK']), $payment);

        $this->assertFalse($result->successful);
    }
}
