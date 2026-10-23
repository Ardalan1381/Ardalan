<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRule;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->first()->id,
        ]);
    }

    private function customerWithPoints(int $points): Customer
    {
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);
        $customer->loyalty_points_balance = $points;
        $customer->save();

        return $customer;
    }

    // --- Loyalty rules CRUD -------------------------------------------------

    public function test_owner_can_view_create_update_and_delete_a_loyalty_rule(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.loyalty-rules.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.loyalty-rules.create'))->assertOk();

        $response = $this->actingAs($owner)->post(route('admin.loyalty-rules.store'), [
            'name' => 'Spend 3000 get 30 points',
            'type' => 'spend_threshold',
            'min_spend_amount' => 3000,
            'points_earned' => 30,
            'is_active' => '1',
        ]);
        $response->assertRedirect(route('admin.loyalty-rules.index'));

        $rule = LoyaltyRule::where('name', 'Spend 3000 get 30 points')->firstOrFail();
        $this->assertSame('spend_threshold', $rule->type);
        $this->assertTrue($rule->is_active);

        $this->actingAs($owner)->get(route('admin.loyalty-rules.edit', $rule))->assertOk();

        $updateResponse = $this->actingAs($owner)->put(route('admin.loyalty-rules.update', $rule), [
            'name' => 'Updated rule name',
            'type' => 'spend_threshold',
            'min_spend_amount' => 5000,
            'points_earned' => 50,
            'is_active' => '1',
        ]);
        $updateResponse->assertRedirect(route('admin.loyalty-rules.index'));
        $this->assertDatabaseHas('loyalty_rules', ['id' => $rule->id, 'name' => 'Updated rule name', 'points_earned' => 50]);

        $deleteResponse = $this->actingAs($owner)->delete(route('admin.loyalty-rules.destroy', $rule));
        $deleteResponse->assertRedirect(route('admin.loyalty-rules.index'));
        $this->assertModelMissing($rule);
    }

    public function test_points_earned_is_required_for_spend_threshold_type(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.loyalty-rules.store'), [
            'name' => 'Missing points',
            'type' => 'spend_threshold',
        ]);

        $response->assertSessionHasErrors('points_earned');
    }

    public function test_buy_quantity_and_get_bonus_points_are_required_for_buy_x_get_y_points_type(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.loyalty-rules.store'), [
            'name' => 'Missing quantities',
            'type' => 'buy_x_get_y_points',
        ]);

        $response->assertSessionHasErrors(['buy_quantity', 'get_bonus_points']);
    }

    public function test_ends_at_must_be_after_or_equal_to_starts_at_for_loyalty_rule(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.loyalty-rules.store'), [
            'name' => 'Bad dates',
            'type' => 'spend_threshold',
            'points_earned' => 10,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('ends_at');
    }

    public function test_cannot_delete_a_loyalty_rule_that_has_ledger_entries(): void
    {
        $owner = $this->userWithRole('owner');
        $rule = LoyaltyRule::create([
            'name' => 'Used rule',
            'type' => 'spend_threshold',
            'points_earned' => 10,
            'is_active' => true,
        ]);
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);
        LoyaltyLedgerEntry::create([
            'customer_id' => $customer->id,
            'type' => 'earn',
            'points' => 10,
            'resulting_balance' => 10,
            'loyalty_rule_id' => $rule->id,
        ]);

        $response = $this->actingAs($owner)->delete(route('admin.loyalty-rules.destroy', $rule));

        $response->assertRedirect(route('admin.loyalty-rules.index'));
        $this->assertModelExists($rule);
    }

    // --- Loyalty rewards CRUD ------------------------------------------------

    public function test_owner_can_view_create_update_and_delete_a_loyalty_reward(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.loyalty-rewards.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.loyalty-rewards.create'))->assertOk();

        $response = $this->actingAs($owner)->post(route('admin.loyalty-rewards.store'), [
            'name' => '$10 off',
            'points_cost' => 100,
            'reward_type' => 'fixed_discount_amount',
            'value' => 1000,
            'is_active' => '1',
        ]);
        $response->assertRedirect(route('admin.loyalty-rewards.index'));

        $reward = LoyaltyReward::where('name', '$10 off')->firstOrFail();

        $this->actingAs($owner)->get(route('admin.loyalty-rewards.edit', $reward))->assertOk();

        $updateResponse = $this->actingAs($owner)->put(route('admin.loyalty-rewards.update', $reward), [
            'name' => 'Updated reward',
            'points_cost' => 150,
            'reward_type' => 'fixed_discount_amount',
            'value' => 1500,
            'is_active' => '1',
        ]);
        $updateResponse->assertRedirect(route('admin.loyalty-rewards.index'));
        $this->assertDatabaseHas('loyalty_rewards', ['id' => $reward->id, 'name' => 'Updated reward', 'points_cost' => 150]);

        $deleteResponse = $this->actingAs($owner)->delete(route('admin.loyalty-rewards.destroy', $reward));
        $deleteResponse->assertRedirect(route('admin.loyalty-rewards.index'));
        $this->assertModelMissing($reward);
    }

    public function test_value_is_required_for_percentage_discount_reward_type(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.loyalty-rewards.store'), [
            'name' => 'Percentage reward',
            'points_cost' => 50,
            'reward_type' => 'percentage_discount',
        ]);

        $response->assertSessionHasErrors('value');
    }

    public function test_free_product_id_is_required_for_free_product_reward_type(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.loyalty-rewards.store'), [
            'name' => 'Free product reward',
            'points_cost' => 50,
            'reward_type' => 'free_product',
        ]);

        $response->assertSessionHasErrors('free_product_id');
    }

    public function test_cannot_delete_a_loyalty_reward_that_has_been_redeemed(): void
    {
        $owner = $this->userWithRole('owner');
        $reward = LoyaltyReward::create([
            'name' => 'Used reward',
            'points_cost' => 50,
            'reward_type' => 'fixed_discount_amount',
            'value' => 500,
            'is_active' => true,
        ]);
        $customer = $this->customerWithPoints(50);
        $order = Order::create([
            'type' => 'takeaway',
            'customer_id' => $customer->id,
            'subtotal' => 5000,
            'grand_total' => 4500,
            'currency' => 'USD',
            'status' => 'completed',
        ]);
        LoyaltyRedemption::create([
            'customer_id' => $customer->id,
            'loyalty_reward_id' => $reward->id,
            'order_id' => $order->id,
            'points_spent' => 50,
            'monetary_value_granted' => 500,
        ]);

        $response = $this->actingAs($owner)->delete(route('admin.loyalty-rewards.destroy', $reward));

        $response->assertRedirect(route('admin.loyalty-rewards.index'));
        $this->assertModelExists($reward);
    }

    // --- Permissions ----------------------------------------------------------

    public function test_kitchen_gets_forbidden_on_every_loyalty_admin_route(): void
    {
        $kitchen = $this->userWithRole('kitchen');
        $rule = LoyaltyRule::create(['name' => 'Rule', 'type' => 'spend_threshold', 'points_earned' => 10, 'is_active' => true]);
        $reward = LoyaltyReward::create(['name' => 'Reward', 'points_cost' => 10, 'reward_type' => 'fixed_discount_amount', 'value' => 100, 'is_active' => true]);
        $customer = $this->customerWithPoints(10);

        $this->actingAs($kitchen)->get(route('admin.loyalty-rules.index'))->assertForbidden();
        $this->actingAs($kitchen)->get(route('admin.loyalty-rules.create'))->assertForbidden();
        $this->actingAs($kitchen)->post(route('admin.loyalty-rules.store'), [])->assertForbidden();
        $this->actingAs($kitchen)->get(route('admin.loyalty-rules.edit', $rule))->assertForbidden();
        $this->actingAs($kitchen)->put(route('admin.loyalty-rules.update', $rule), [])->assertForbidden();
        $this->actingAs($kitchen)->delete(route('admin.loyalty-rules.destroy', $rule))->assertForbidden();

        $this->actingAs($kitchen)->get(route('admin.loyalty-rewards.index'))->assertForbidden();
        $this->actingAs($kitchen)->get(route('admin.loyalty-rewards.create'))->assertForbidden();
        $this->actingAs($kitchen)->post(route('admin.loyalty-rewards.store'), [])->assertForbidden();
        $this->actingAs($kitchen)->get(route('admin.loyalty-rewards.edit', $reward))->assertForbidden();
        $this->actingAs($kitchen)->put(route('admin.loyalty-rewards.update', $reward), [])->assertForbidden();
        $this->actingAs($kitchen)->delete(route('admin.loyalty-rewards.destroy', $reward))->assertForbidden();

        $this->actingAs($kitchen)->patch(route('admin.customers.loyalty.adjust', $customer), ['points' => 5])->assertForbidden();
    }

    public function test_waiter_can_view_but_not_manage_loyalty_rules_and_rewards(): void
    {
        $waiter = $this->userWithRole('waiter');
        $rule = LoyaltyRule::create(['name' => 'Rule', 'type' => 'spend_threshold', 'points_earned' => 10, 'is_active' => true]);
        $reward = LoyaltyReward::create(['name' => 'Reward', 'points_cost' => 10, 'reward_type' => 'fixed_discount_amount', 'value' => 100, 'is_active' => true]);
        $customer = $this->customerWithPoints(10);

        // Waiter has loyalty.view + loyalty.redeem, so viewing lists is fine...
        $this->actingAs($waiter)->get(route('admin.loyalty-rules.index'))->assertOk();
        $this->actingAs($waiter)->get(route('admin.loyalty-rewards.index'))->assertOk();

        // ...but not creating/updating/deleting rules or rewards.
        $this->actingAs($waiter)->get(route('admin.loyalty-rules.create'))->assertForbidden();
        $this->actingAs($waiter)->post(route('admin.loyalty-rules.store'), [])->assertForbidden();
        $this->actingAs($waiter)->get(route('admin.loyalty-rules.edit', $rule))->assertForbidden();
        $this->actingAs($waiter)->put(route('admin.loyalty-rules.update', $rule), [])->assertForbidden();
        $this->actingAs($waiter)->delete(route('admin.loyalty-rules.destroy', $rule))->assertForbidden();

        $this->actingAs($waiter)->get(route('admin.loyalty-rewards.create'))->assertForbidden();
        $this->actingAs($waiter)->post(route('admin.loyalty-rewards.store'), [])->assertForbidden();
        $this->actingAs($waiter)->get(route('admin.loyalty-rewards.edit', $reward))->assertForbidden();
        $this->actingAs($waiter)->put(route('admin.loyalty-rewards.update', $reward), [])->assertForbidden();
        $this->actingAs($waiter)->delete(route('admin.loyalty-rewards.destroy', $reward))->assertForbidden();

        // Nor can they perform manual balance adjustments (loyalty.manage only).
        $this->actingAs($waiter)->patch(route('admin.customers.loyalty.adjust', $customer), ['points' => 5])->assertForbidden();
        $this->assertSame(10, $customer->fresh()->loyalty_points_balance);
    }

    // --- Customer loyalty section ----------------------------------------------

    public function test_customer_edit_page_shows_the_loyalty_section(): void
    {
        $owner = $this->userWithRole('owner');
        $customer = $this->customerWithPoints(42);
        LoyaltyLedgerEntry::create([
            'customer_id' => $customer->id,
            'type' => 'manual_adjustment',
            'points' => 42,
            'resulting_balance' => 42,
            'notes' => 'Welcome bonus',
        ]);

        $response = $this->actingAs($owner)->get(route('admin.customers.edit', $customer));

        $response->assertOk();
        $response->assertSee('42');
        $response->assertSee('Welcome bonus');
    }

    public function test_manual_adjustment_moves_the_balance_and_records_a_ledger_entry(): void
    {
        $owner = $this->userWithRole('owner');
        $customer = $this->customerWithPoints(10);

        $response = $this->actingAs($owner)->patch(route('admin.customers.loyalty.adjust', $customer), [
            'points' => 15,
            'notes' => 'Goodwill credit',
        ]);

        $response->assertRedirect(route('admin.customers.edit', $customer));
        $customer->refresh();
        $this->assertSame(25, $customer->loyalty_points_balance);

        $entry = LoyaltyLedgerEntry::where('customer_id', $customer->id)->sole();
        $this->assertSame('manual_adjustment', $entry->type);
        $this->assertSame(15, $entry->points);
        $this->assertSame(25, $entry->resulting_balance);
        $this->assertSame('Goodwill credit', $entry->notes);
        $this->assertSame($owner->id, $entry->created_by_user_id);
    }

    public function test_manual_adjustment_rejects_an_over_negative_adjustment(): void
    {
        $owner = $this->userWithRole('owner');
        $customer = $this->customerWithPoints(10);

        $response = $this->actingAs($owner)->patch(route('admin.customers.loyalty.adjust', $customer), [
            'points' => -20,
        ]);

        $response->assertRedirect(route('admin.customers.edit', $customer));
        $response->assertSessionHasErrors('points');
        $this->assertSame(10, $customer->fresh()->loyalty_points_balance);
        $this->assertSame(0, LoyaltyLedgerEntry::count());
    }

    public function test_manual_adjustment_rejects_a_zero_points_value(): void
    {
        $owner = $this->userWithRole('owner');
        $customer = $this->customerWithPoints(10);

        $response = $this->actingAs($owner)->patch(route('admin.customers.loyalty.adjust', $customer), [
            'points' => 0,
        ]);

        $response->assertSessionHasErrors('points');
        $this->assertSame(10, $customer->fresh()->loyalty_points_balance);
    }
}
