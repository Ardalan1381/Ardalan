<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->first()->id]);
    }

    private function placeOrder(): Order
    {
        $product = Product::factory()->create();

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        return Order::firstOrFail();
    }

    public function test_kitchen_role_can_view_the_kitchen_display(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->userWithRole('kitchen'))
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_user_without_orders_permission_cannot_view_kitchen_display(): void
    {
        $role = Role::create(['name' => 'nobody', 'label' => 'Nobody']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get(route('admin.kitchen.index'))->assertForbidden();
    }

    public function test_orders_are_grouped_under_their_current_status_column(): void
    {
        $order = $this->placeOrder();

        $response = $this->actingAs($this->userWithRole('owner'))->get(route('admin.kitchen.index'));

        $response->assertOk();
        $response->assertSeeInOrder([__('orders.status.pending'), $order->order_number]);
    }

    public function test_an_order_past_the_delay_threshold_is_flagged_as_delayed(): void
    {
        BusinessSetting::current()->update(['kitchen_delay_threshold_minutes' => 10]);
        $order = $this->placeOrder();
        $order->update(['status_changed_at' => now()->subMinutes(30)]);

        $this->actingAs($this->userWithRole('owner'))
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee(__('admin.kitchen.delayed'));
    }

    public function test_marking_an_order_from_the_kitchen_display_advances_its_status(): void
    {
        $order = $this->placeOrder();
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->status_changed_at);
    }

    public function test_completed_orders_do_not_appear_on_the_kitchen_display(): void
    {
        $order = $this->placeOrder();
        $owner = $this->userWithRole('owner');

        foreach (['confirmed', 'preparing', 'ready', 'completed'] as $status) {
            $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => $status]);
        }

        $this->actingAs($owner)
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertDontSee($order->order_number);
    }
}
