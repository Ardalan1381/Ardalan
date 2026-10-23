<?php

namespace Tests\Concurrency;

use App\Models\Customer;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Table;
use App\Models\TableSection;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Database\Seeders\RolePermissionSeeder;

/**
 * AUD-01/02/03/06/07/09 — each test releases several workers at the same
 * instant against the same row and asserts the business invariant that
 * must hold however the race resolves.
 */
class CriticalOperationsConcurrencyTest extends ConcurrencyTestCase
{
    private const WORKERS = 6;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('name', 'owner')->value('id')]);
    }

    private function productWithRecipe(int $price = 1000): array
    {
        $gram = InventoryUnit::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'mass', 'base_factor' => 1]);
        $coffee = InventoryItem::create(['name' => 'Coffee', 'base_unit_id' => $gram->id, 'is_active' => true]);
        app(StockMovementService::class)->record($coffee, 'opening_balance', '1000');

        $product = Product::factory()->create(['base_price' => $price]);
        $product->recipeItems()->create(['inventory_item_id' => $coffee->id, 'quantity' => '18', 'unit_id' => $gram->id]);

        return [$product, $coffee];
    }

    private function takeawayOrder(Product $product, ?Customer $customer = null, int $quantity = 1): Order
    {
        $order = app(\App\Actions\PlaceOrderAction::class)->execute([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ], null);

        if ($customer) {
            $order->forceFill(['customer_id' => $customer->id])->save();
        }

        return $order->fresh();
    }

    public function test_aud01_concurrent_confirmations_consume_stock_exactly_once(): void
    {
        [$product, $coffee] = $this->productWithRecipe();
        $order = $this->takeawayOrder($product, quantity: 2);

        $outcomes = $this->race('transition', self::WORKERS, ['order_id' => $order->id, 'status' => 'confirmed', 'user_id' => $this->owner->id]);

        $this->assertSame(1, $this->successes($outcomes), json_encode($outcomes));
        $this->assertSame('964.0000', $coffee->fresh()->current_stock);
        $this->assertSame(1, StockMovement::where('type', 'sale_consumption')->count());
        $this->assertSame(1, $order->statusHistory()->where('to_status', 'confirmed')->count());
    }

    public function test_aud01_concurrent_completions_award_loyalty_exactly_once(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);
        $customer = Customer::create(['name' => 'Ali', 'phone' => '0912', 'status' => 'active']);
        LoyaltyRule::create(['name' => 'Every order', 'type' => 'spend_threshold', 'min_spend_amount' => 0, 'points_earned' => 10, 'is_active' => true]);
        $order = $this->takeawayOrder($product, $customer);
        $order->forceFill(['status' => 'ready'])->save();

        $outcomes = $this->race('transition', self::WORKERS, ['order_id' => $order->id, 'status' => 'completed', 'user_id' => $this->owner->id]);

        $this->assertSame(1, $this->successes($outcomes), json_encode($outcomes));
        $this->assertSame(1, LoyaltyLedgerEntry::where('order_id', $order->id)->count());
        $this->assertSame(10, $customer->fresh()->loyalty_points_balance);
    }

    public function test_aud02_concurrent_payments_cannot_overpay_an_order(): void
    {
        $product = Product::factory()->create(['base_price' => 1000]);
        $order = $this->takeawayOrder($product);

        $outcomes = $this->race('record_payment', self::WORKERS, [
            'order_id' => $order->id,
            'user_id' => $this->owner->id,
            'data' => ['method' => 'card', 'amount' => $order->grand_total, 'status' => 'paid'],
        ]);

        $this->assertSame(1, $this->successes($outcomes), json_encode($outcomes));
        $this->assertSame($order->grand_total, (int) Payment::where('order_id', $order->id)->where('status', 'paid')->sum('amount'));
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_aud02_concurrent_confirmation_of_pending_payments_cannot_overpay(): void
    {
        $product = Product::factory()->create(['base_price' => 1000]);
        $order = $this->takeawayOrder($product);

        $paymentIds = collect(range(1, self::WORKERS))->map(fn () => $order->payments()->create([
            'method' => 'card', 'amount' => $order->grand_total, 'status' => 'pending',
        ])->id);

        $outcomes = $this->race('confirm_payment', self::WORKERS, $paymentIds->map(fn ($id) => [
            'payment_id' => $id, 'user_id' => $this->owner->id,
        ])->all());

        $this->assertSame(1, $this->successes($outcomes), json_encode($outcomes));
        $this->assertSame($order->grand_total, (int) Payment::where('order_id', $order->id)->where('status', 'paid')->sum('amount'));
    }

    public function test_aud03_retried_checkout_with_the_same_idempotency_key_creates_one_order(): void
    {
        $product = Product::factory()->create(['base_price' => 1000]);

        $outcomes = $this->race('place_order', self::WORKERS, ['data' => [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555',
            'idempotency_key' => '6f1c1d52-2a43-4d8e-9a0e-1b5d7c1e9f00',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]]);

        $this->assertSame(self::WORKERS, $this->successes($outcomes), json_encode($outcomes));
        $this->assertSame(1, Order::count());
        $this->assertCount(1, array_unique(array_column($outcomes, 'result')));
    }

    public function test_aud06_concurrent_dine_in_requests_share_one_open_table_session(): void
    {
        $section = TableSection::factory()->create();
        $table = Table::factory()->create(['table_section_id' => $section->id, 'status' => 'available', 'is_active' => true]);

        $outcomes = $this->race('open_table_session', self::WORKERS, ['table_id' => $table->id]);

        $this->assertSame(self::WORKERS, $this->successes($outcomes), json_encode($outcomes));
        $this->assertSame(1, TableSession::where('table_id', $table->id)->where('status', 'open')->count());
    }

    public function test_aud07_concurrent_checkouts_cannot_exceed_a_discount_usage_limit(): void
    {
        // A different product per worker: CartPricer locks each product row,
        // so a shared product would serialise the workers and hide the race.
        $products = Product::factory()->count(self::WORKERS)->create(['base_price' => 1000]);
        $discount = Discount::create([
            'name' => 'First order only', 'type' => 'fixed_amount', 'value' => 100,
            'applies_to' => 'order', 'priority' => 1, 'is_stackable' => false, 'is_active' => true,
            'usage_limit' => 1,
        ]);

        $outcomes = $this->race('place_order', self::WORKERS, $products->map(fn (Product $product) => ['data' => [
            'type' => 'takeaway', 'customer_name' => 'Jane', 'customer_phone' => '555',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]])->all());

        $this->assertSame(self::WORKERS, $this->successes($outcomes), json_encode($outcomes));
        $this->assertSame(1, DiscountRedemption::where('discount_id', $discount->id)->count());
        $this->assertSame(1, $discount->fresh()->used_count);
    }

    public function test_aud09_concurrent_refunds_cannot_exceed_the_paid_amount(): void
    {
        $product = Product::factory()->create(['base_price' => 1000]);
        $order = $this->takeawayOrder($product);
        $payment = $order->payments()->create(['method' => 'cash', 'amount' => $order->grand_total, 'status' => 'paid', 'paid_at' => now()]);
        $order->syncPaymentStatus();

        $outcomes = $this->race('refund', self::WORKERS, [
            'payment_id' => $payment->id,
            'user_id' => $this->owner->id,
            'data' => ['amount' => $order->grand_total, 'refund_method' => 'cash', 'reason' => 'race'],
        ]);

        $this->assertSame(1, $this->successes($outcomes), json_encode($outcomes));
        $this->assertSame($order->grand_total, (int) Refund::where('payment_id', $payment->id)->sum('amount'));
    }
}
