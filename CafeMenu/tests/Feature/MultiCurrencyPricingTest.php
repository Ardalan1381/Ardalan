<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Additional display currencies are a menu-only feature: a manually-entered
 * reference price shown when browsing in another language. Nothing here
 * ever changes what an order actually charges — that always stays in the
 * business's home currency, verified by the untouched CartPricer/
 * PlaceOrderAction tests elsewhere; these tests only cover the display
 * layer and the online-payment-gateway safety gate.
 */
class MultiCurrencyPricingTest extends TestCase
{
    use RefreshDatabase;

    private function enableUsdForEnglish(): void
    {
        BusinessSetting::current()->update([
            'locale' => 'fa',
            'currency' => 'تومان',
            'currency_minor_units' => 0,
            'display_currencies' => [
                'en' => ['is_active' => true, 'code' => 'USD', 'symbol' => '$', 'minor_units' => 2],
            ],
        ]);
    }

    public function test_price_for_home_locale_uses_the_home_currency_regardless_of_overrides(): void
    {
        $this->enableUsdForEnglish();
        $product = Product::factory()->create(['base_price' => 65000]);
        $product->prices()->create(['locale' => 'en', 'price' => 250]);

        $price = $product->priceFor(null, 'fa');

        $this->assertSame(65000, $price['amount']);
        $this->assertTrue($price['is_home']);
        $this->assertSame('تومان', $price['currency_code']);
    }

    public function test_price_for_a_locale_with_an_override_uses_it(): void
    {
        $this->enableUsdForEnglish();
        $product = Product::factory()->create(['base_price' => 65000]);
        $product->prices()->create(['locale' => 'en', 'price' => 250]);

        $price = $product->priceFor(null, 'en');

        $this->assertSame(250, $price['amount']);
        $this->assertFalse($price['is_home']);
        $this->assertSame('USD', $price['currency_code']);
        $this->assertSame('$', $price['currency_symbol']);
        $this->assertSame(2, $price['minor_units']);
    }

    public function test_price_for_a_locale_without_an_override_falls_back_to_home_price(): void
    {
        $this->enableUsdForEnglish();
        $product = Product::factory()->create(['base_price' => 65000]);

        $price = $product->priceFor(null, 'en');

        $this->assertSame(65000, $price['amount']);
        $this->assertTrue($price['is_home']);
    }

    public function test_price_for_a_locale_whose_currency_was_disabled_falls_back_to_home_price(): void
    {
        BusinessSetting::current()->update([
            'locale' => 'fa',
            'display_currencies' => ['en' => ['is_active' => false, 'code' => 'USD', 'symbol' => '$', 'minor_units' => 2]],
        ]);
        $product = Product::factory()->create(['base_price' => 65000]);
        $product->prices()->create(['locale' => 'en', 'price' => 250]);

        $price = $product->priceFor(null, 'en');

        $this->assertTrue($price['is_home']);
        $this->assertSame(65000, $price['amount']);
    }

    public function test_discount_override_reports_its_own_original_amount(): void
    {
        $this->enableUsdForEnglish();
        $product = Product::factory()->create(['base_price' => 65000]);
        $product->prices()->create(['locale' => 'en', 'price' => 300, 'discount_price' => 250]);

        $price = $product->priceFor(null, 'en');

        $this->assertSame(250, $price['amount']);
        $this->assertSame(300, $price['original_amount']);
    }

    public function test_menu_shows_the_override_price_when_browsing_in_that_locale(): void
    {
        $this->enableUsdForEnglish();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
            'base_price' => 65000,
        ]);
        $product->prices()->create(['locale' => 'en', 'price' => 250]);

        $response = $this->withSession(['locale' => 'en'])->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('2.50');
        $response->assertDontSee('65,000');
    }

    public function test_menu_falls_back_to_home_price_in_a_locale_with_no_override(): void
    {
        $this->enableUsdForEnglish();
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
            'base_price' => 65000,
        ]);

        $response = $this->withSession(['locale' => 'en'])->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('65,000');
    }

    public function test_online_gateways_are_hidden_when_browsing_in_a_non_home_currency(): void
    {
        $this->enableUsdForEnglish();
        BusinessSetting::current()->update(['enabled_online_gateways' => ['zarinpal']]);
        app()->setLocale('en');

        $available = app(PaymentGatewayManager::class)->availableForCustomers();

        $this->assertSame([], $available);
    }
}
