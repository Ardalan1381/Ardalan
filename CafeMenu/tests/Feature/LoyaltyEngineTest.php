<?php

namespace Tests\Feature;

use App\Actions\Loyalty\RedeemLoyaltyRewardAction;
use App\Actions\TransitionOrderStatusAction;
use App\Models\Customer;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRule;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Loyalty\LoyaltyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoyaltyEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function ownerUser(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
    }

    /**
     * loyalty_points_balance is deliberately excluded from Customer's
     * $fillable (only LoyaltyLedgerService may write it), so tests that
     * need a starting balance must set it directly, the same way
     * LoyaltyLedgerService itself does.
     */
    private function customerWithPoints(int $points): Customer
    {
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);
        $customer->loyalty_points_balance = $points;
        $customer->save();

        return $customer;
    }

    private function placeOrderForCustomer(Customer $customer, int $basePrice = 5000, int $quantity = 1): Order
    {
        $product = Product::factory()->create(['base_price' => $basePrice]);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ])->assertOk();

        $order = Order::latest('id')->firstOrFail();
        $order->update(['customer_id' => $customer->id]);

        return $order;
    }

    private function completeOrder(Order $order): Order
    {
        $owner = $this->ownerUser();
        app(TransitionOrderStatusAction::class)->execute($order->fresh(), 'confirmed', $owner);
        app(TransitionOrderStatusAction::class)->execute($order->fresh(), 'preparing', $owner);
        app(TransitionOrderStatusAction::class)->execute($order->fresh(), 'ready', $owner);
        app(TransitionOrderStatusAction::class)->execute($order->fresh(), 'completed', $owner);

        return $order->fresh();
    }

    public function test_spend_threshold_rule_awards_points_on_order_completion(): void
    {
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);
        LoyaltyRule::create([
            'name' => 'Spend 3000 get 30 points',
            'type' => 'spend_threshold',
            'min_spend_amount' => 3000,
            'points_earned' => 30,
            'is_active' => true,
        ]);

        $order = $this->placeOrderForCustomer($customer, basePrice: 5000);
        $this->completeOrder($order);

        $customer->refresh();
        $this->assertSame(30, $customer->loyalty_points_balance);
        $this->assertSame(1, LoyaltyLedgerEntry::count());
        $entry = LoyaltyLedgerEntry::sole();
        $this->assertSame('earn', $entry->type);
        $this->assertSame(30, $entry->points);
        $this->assertSame($order->id, $entry->order_id);
    }

    public function test_spend_threshold_rule_does_not_award_points_below_the_minimum(): void
    {
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);
        LoyaltyRule::create([
            'name' => 'Spend 10000 get 30 points',
            'type' => 'spend_threshold',
            'min_spend_amount' => 10000,
            'points_earned' => 30,
            'is_active' => true,
        ]);

        $order = $this->placeOrderForCustomer($customer, basePrice: 5000);
        $this->completeOrder($order);

        $this->assertSame(0, $customer->fresh()->loyalty_points_balance);
        $this->assertSame(0, LoyaltyLedgerEntry::count());
    }

    public function test_buy_x_get_y_points_rule_awards_bonus_points_per_group(): void
    {
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);
        LoyaltyRule::create([
            'name' => 'Every 2 items earns 5 bonus points',
            'type' => 'buy_x_get_y_points',
            'buy_quantity' => 2,
            'get_bonus_points' => 5,
            'is_active' => true,
        ]);

        $order = $this->placeOrderForCustomer($customer, basePrice: 1000, quantity: 5);
        $this->completeOrder($order);

        // floor(5 / 2) * 5 = 10
        $this->assertSame(10, $customer->fresh()->loyalty_points_balance);
    }

    public function test_guest_orders_never_earn_points(): void
    {
        LoyaltyRule::create([
            'name' => 'Spend anything get 30 points',
            'type' => 'spend_threshold',
            'points_earned' => 30,
            'is_active' => true,
        ]);

        $product = Product::factory()->create(['base_price' => 5000]);
        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->completeOrder($order);

        $this->assertSame(0, LoyaltyLedgerEntry::count());
    }

    public function test_inactive_rule_does_not_award_points(): void
    {
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);
        LoyaltyRule::create([
            'name' => 'Disabled rule',
            'type' => 'spend_threshold',
            'points_earned' => 30,
            'is_active' => false,
        ]);

        $order = $this->placeOrderForCustomer($customer);
        $this->completeOrder($order);

        $this->assertSame(0, $customer->fresh()->loyalty_points_balance);
    }

    public function test_ledger_service_blocks_a_negative_balance(): void
    {
        $customer = $this->customerWithPoints(10);

        $this->expectException(ValidationException::class);
        app(LoyaltyLedgerService::class)->post($customer, 'manual_adjustment', -20);
    }

    public function test_ledger_service_records_a_traceable_entry_and_updates_the_cached_balance(): void
    {
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);

        app(LoyaltyLedgerService::class)->post($customer, 'manual_adjustment', 50, notes: 'Goodwill credit');

        $customer->refresh();
        $this->assertSame(50, $customer->loyalty_points_balance);
        $entry = LoyaltyLedgerEntry::sole();
        $this->assertSame(50, $entry->resulting_balance);
        $this->assertSame('Goodwill credit', $entry->notes);
    }

    public function test_redeem_fixed_discount_amount_reward_reduces_the_cart_and_spends_points(): void
    {
        $customer = $this->customerWithPoints(100);
        $reward = LoyaltyReward::create([
            'name' => '$10 off',
            'points_cost' => 100,
            'reward_type' => 'fixed_discount_amount',
            'value' => 1000,
            'is_active' => true,
        ]);

        $lineItems = [
            ['product' => Product::factory()->create(['base_price' => 5000]), 'quantity' => 1, 'line_total' => 5000, 'discount_total' => 0],
        ];

        $result = app(RedeemLoyaltyRewardAction::class)->execute($customer, $reward, $lineItems);

        $this->assertSame(1000, $result['amount']);
        $this->assertSame(100, $result['points_spent']);
        $this->assertSame(1000, $lineItems[0]['discount_total']);
        $this->assertSame(0, $customer->fresh()->loyalty_points_balance);
        $this->assertSame(1, LoyaltyLedgerEntry::where('type', 'redeem')->count());
    }

    public function test_redeem_free_product_reward_appends_a_fully_discounted_line(): void
    {
        $customer = $this->customerWithPoints(50);
        $freebie = Product::factory()->create(['base_price' => 2500]);
        $reward = LoyaltyReward::create([
            'name' => 'Free cookie',
            'points_cost' => 50,
            'reward_type' => 'free_product',
            'free_product_id' => $freebie->id,
            'is_active' => true,
        ]);

        $lineItems = [
            ['product' => Product::factory()->create(['base_price' => 5000]), 'quantity' => 1, 'line_total' => 5000, 'discount_total' => 0],
        ];

        $result = app(RedeemLoyaltyRewardAction::class)->execute($customer, $reward, $lineItems);

        $this->assertCount(2, $lineItems);
        $this->assertSame(2500, $result['amount']);
        $this->assertSame(2500, $lineItems[1]['discount_total']);
        $this->assertSame(0, $customer->fresh()->loyalty_points_balance);
    }

    public function test_redeem_fails_with_insufficient_points(): void
    {
        $customer = $this->customerWithPoints(10);
        $reward = LoyaltyReward::create([
            'name' => '$10 off',
            'points_cost' => 100,
            'reward_type' => 'fixed_discount_amount',
            'value' => 1000,
            'is_active' => true,
        ]);

        $lineItems = [
            ['product' => Product::factory()->create(['base_price' => 5000]), 'quantity' => 1, 'line_total' => 5000, 'discount_total' => 0],
        ];

        $this->expectException(ValidationException::class);
        app(RedeemLoyaltyRewardAction::class)->execute($customer, $reward, $lineItems);
    }
}
