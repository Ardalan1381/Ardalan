<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountManagementTest extends TestCase
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

    public function test_owner_can_view_discounts_list(): void
    {
        $owner = $this->userWithRole('owner');
        $discount = Discount::create(['name' => '10% off', 'type' => 'percentage', 'value' => 1000, 'applies_to' => 'order']);

        $this->actingAs($owner)->get(route('admin.discounts.index'))->assertOk()->assertSee($discount->name);
        $this->actingAs($owner)->get(route('admin.discounts.create'))->assertOk();
        $this->actingAs($owner)->get(route('admin.discounts.edit', $discount))->assertOk();
    }

    public function test_kitchen_cannot_view_discounts_list(): void
    {
        $kitchen = $this->userWithRole('kitchen');

        $this->actingAs($kitchen)->get(route('admin.discounts.index'))->assertForbidden();
    }

    public function test_cashier_can_view_discounts_but_cannot_create_them(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->get(route('admin.discounts.index'))->assertOk();
        $this->actingAs($cashier)->get(route('admin.discounts.create'))->assertForbidden();
        $this->actingAs($cashier)->post(route('admin.discounts.store'), ['name' => 'x'])->assertForbidden();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Order discount',
            'type' => 'percentage',
            'value' => 1000,
            'applies_to' => 'order',
            'priority' => 0,
        ], $overrides);
    }

    public function test_owner_can_create_a_discount_and_pivots_are_synced(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $customer = Customer::create(['name' => 'Jane Doe']);
        $group = CustomerGroup::create(['name' => 'VIP']);
        $campaign = Campaign::create(['name' => 'Summer Sale']);

        $response = $this->actingAs($owner)->post(route('admin.discounts.store'), $this->validPayload([
            'applies_to' => 'specific_products',
            'campaign_id' => $campaign->id,
            'product_ids' => [$product->id],
            'category_ids' => [$category->id],
            'customer_ids' => [$customer->id],
            'customer_group_ids' => [$group->id],
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'days_of_week' => [1 => '1', 3 => '1'],
        ]));

        $response->assertRedirect(route('admin.discounts.index'));

        $discount = Discount::where('name', 'Order discount')->firstOrFail();
        $this->assertSame('specific_products', $discount->applies_to);
        $this->assertSame($campaign->id, $discount->campaign_id);
        $this->assertSame([1, 3], $discount->days_of_week);
        $this->assertStringStartsWith('09:00', $discount->start_time);
        $this->assertStringStartsWith('17:00', $discount->end_time);

        $this->assertDatabaseHas('discount_products', ['discount_id' => $discount->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('discount_categories', ['discount_id' => $discount->id, 'category_id' => $category->id]);
        $this->assertDatabaseHas('discount_customers', ['discount_id' => $discount->id, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('discount_customer_groups', ['discount_id' => $discount->id, 'customer_group_id' => $group->id]);
    }

    public function test_value_is_required_for_percentage_type(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.discounts.store'), $this->validPayload(['value' => null]));

        $response->assertSessionHasErrors('value');
    }

    public function test_value_is_not_required_for_half_price_type(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.discounts.store'), $this->validPayload([
            'type' => 'half_price',
            'value' => null,
            'applies_to' => 'specific_categories',
        ]));

        $response->assertRedirect(route('admin.discounts.index'));
        $this->assertDatabaseHas('discounts', ['name' => 'Order discount', 'type' => 'half_price']);
    }

    public function test_buy_quantity_and_get_quantity_are_required_for_buy_x_get_y_type(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.discounts.store'), $this->validPayload([
            'type' => 'buy_x_get_y',
            'value' => null,
        ]));

        $response->assertSessionHasErrors(['buy_quantity', 'get_quantity']);
    }

    public function test_free_item_product_is_required_for_free_item_type(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.discounts.store'), $this->validPayload([
            'type' => 'free_item',
            'value' => null,
        ]));

        $response->assertSessionHasErrors('free_item_product_id');
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.discounts.store'), $this->validPayload([
            'start_time' => '17:00',
            'end_time' => '09:00',
        ]));

        $response->assertSessionHasErrors('end_time');
    }

    public function test_ends_at_must_be_after_or_equal_to_starts_at(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.discounts.store'), $this->validPayload([
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-01-01',
        ]));

        $response->assertSessionHasErrors('ends_at');
    }

    public function test_owner_can_update_a_discount_and_resync_pivots(): void
    {
        $owner = $this->userWithRole('owner');
        $discount = Discount::create(['name' => 'Original', 'type' => 'percentage', 'value' => 500, 'applies_to' => 'order']);
        $firstProduct = Product::factory()->create();
        $secondProduct = Product::factory()->create();
        $discount->products()->attach($firstProduct->id);

        $response = $this->actingAs($owner)->put(route('admin.discounts.update', $discount), $this->validPayload([
            'name' => 'Updated name',
            'applies_to' => 'specific_products',
            'product_ids' => [$secondProduct->id],
        ]));

        $response->assertRedirect(route('admin.discounts.index'));
        $this->assertDatabaseHas('discounts', ['id' => $discount->id, 'name' => 'Updated name']);
        $this->assertDatabaseMissing('discount_products', ['discount_id' => $discount->id, 'product_id' => $firstProduct->id]);
        $this->assertDatabaseHas('discount_products', ['discount_id' => $discount->id, 'product_id' => $secondProduct->id]);
    }

    public function test_cannot_delete_a_discount_that_has_been_redeemed(): void
    {
        $owner = $this->userWithRole('owner');
        $discount = Discount::create([
            'name' => 'Used discount', 'type' => 'percentage', 'value' => 500, 'applies_to' => 'order',
        ]);
        // used_count is deliberately not mass-assignable (it's engine-managed),
        // so it has to be set directly to simulate a discount with history.
        $discount->used_count = 1;
        $discount->save();

        $response = $this->actingAs($owner)->delete(route('admin.discounts.destroy', $discount));

        $response->assertRedirect(route('admin.discounts.index'));
        $this->assertModelExists($discount);
    }

    public function test_can_delete_an_unused_discount(): void
    {
        $owner = $this->userWithRole('owner');
        $discount = Discount::create(['name' => 'Unused', 'type' => 'percentage', 'value' => 500, 'applies_to' => 'order']);

        $this->actingAs($owner)->delete(route('admin.discounts.destroy', $discount));

        $this->assertModelMissing($discount);
    }
}
