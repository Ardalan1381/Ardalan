<?php

namespace Tests\Feature\Payments;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\Payments\Gateways\MellatGateway;
use App\Services\Payments\Gateways\PaymentGatewayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MellatGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BusinessSetting::current()->update(['currency' => 'IRT', 'currency_minor_units' => 0]);
        config([
            'payment.gateways.mellat.terminal_id' => '12345',
            'payment.gateways.mellat.username' => 'user',
            'payment.gateways.mellat.password' => 'pass',
        ]);
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

    private function soapResponse(string $returnValue): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
   <soap:Body>
      <bpPayRequestResponse xmlns="http://interfaces.core.sw.bps.com/">
         <return>{$returnValue}</return>
      </bpPayRequestResponse>
   </soap:Body>
</soap:Envelope>
XML;
    }

    public function test_initiate_stores_ref_id_and_returns_auto_submit_form(): void
    {
        Http::fake([
            '*' => Http::response($this->soapResponse('0,REF-ABC-123'), 200, ['Content-Type' => 'text/xml']),
        ]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);

        $result = (new MellatGateway)->initiate($payment, 'https://example.test/callback');

        $this->assertSame('POST', $result->method);
        $this->assertSame('REF-ABC-123', $result->fields['RefId']);
        $this->assertSame('REF-ABC-123', $payment->fresh()->gateway_token);
    }

    public function test_initiate_throws_on_nonzero_result_code(): void
    {
        Http::fake(['*' => Http::response($this->soapResponse('21'), 200, ['Content-Type' => 'text/xml'])]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);

        $this->expectException(PaymentGatewayException::class);

        (new MellatGateway)->initiate($payment, 'https://example.test/callback');
    }

    public function test_verify_fails_fast_on_nonzero_rescode_without_calling_gateway(): void
    {
        Http::fake();

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = 'REF-ABC-123';
        $payment->save();

        $result = (new MellatGateway)->verify(
            Request::create('/', 'POST', ['RefId' => 'REF-ABC-123', 'ResCode' => '17', 'SaleOrderId' => $payment->id, 'SaleReferenceId' => 'SALE-1']),
            $payment,
        );

        $this->assertFalse($result->successful);
        Http::assertNothingSent();
    }

    public function test_verify_succeeds_through_full_verify_and_settle_path(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push($this->soapResponse('0'))
                ->push($this->soapResponse('0')),
        ]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = 'REF-ABC-123';
        $payment->save();

        $result = (new MellatGateway)->verify(
            Request::create('/', 'POST', ['RefId' => 'REF-ABC-123', 'ResCode' => '0', 'SaleOrderId' => $payment->id, 'SaleReferenceId' => 'SALE-1']),
            $payment,
        );

        $this->assertTrue($result->successful);
        $this->assertSame('SALE-1', $result->gatewayReference);
        Http::assertSentCount(2);
    }

    public function test_verify_fails_when_verify_step_itself_fails(): void
    {
        Http::fake(['*' => Http::response($this->soapResponse('45'), 200, ['Content-Type' => 'text/xml'])]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = 'REF-ABC-123';
        $payment->save();

        $result = (new MellatGateway)->verify(
            Request::create('/', 'POST', ['RefId' => 'REF-ABC-123', 'ResCode' => '0', 'SaleOrderId' => $payment->id, 'SaleReferenceId' => 'SALE-1']),
            $payment,
        );

        $this->assertFalse($result->successful);
        Http::assertSentCount(1);
    }

    public function test_verify_fails_when_settle_step_fails_after_verify_succeeds(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push($this->soapResponse('0'))
                ->push($this->soapResponse('12')),
        ]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = 'REF-ABC-123';
        $payment->save();

        $result = (new MellatGateway)->verify(
            Request::create('/', 'POST', ['RefId' => 'REF-ABC-123', 'ResCode' => '0', 'SaleOrderId' => $payment->id, 'SaleReferenceId' => 'SALE-1']),
            $payment,
        );

        $this->assertFalse($result->successful);
        Http::assertSentCount(2);
    }
}
