<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTest extends TestCase
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

    public function test_owner_can_view_orders_index_and_detail(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        $this->actingAs($owner)->get(route('admin.orders.index'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertOk()->assertSee($order->order_number);
    }

    public function test_user_without_orders_permission_cannot_view_orders(): void
    {
        $role = Role::create(['name' => 'nobody', 'label' => 'Nobody']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $order = $this->placeOrder();

        $this->actingAs($user)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.orders.show', $order))->assertForbidden();
    }

    public function test_valid_status_transition_succeeds_and_records_history(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        $response = $this->actingAs($owner)->patch(route('admin.orders.status', $order), [
            'status' => 'confirmed',
            'note' => 'Kitchen notified',
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));
        $order->refresh();
        $this->assertSame('confirmed', $order->status);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'changed_by_user_id' => $owner->id,
            'note' => 'Kitchen notified',
        ]);
    }

    public function test_full_happy_path_lifecycle(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        foreach (['confirmed', 'preparing', 'ready', 'completed'] as $status) {
            $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => $status])
                ->assertRedirect(route('admin.orders.show', $order));
            $order->refresh();
            $this->assertSame($status, $order->status);
        }

        $this->assertSame(5, $order->statusHistory()->count()); // created + 4 transitions
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        // pending -> ready is not a legal jump.
        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'ready']);

        $order->refresh();
        $this->assertSame('pending', $order->status);
    }

    public function test_completed_order_cannot_be_transitioned_further(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        foreach (['confirmed', 'preparing', 'ready', 'completed'] as $status) {
            $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => $status]);
            $order->refresh();
        }

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);

        $order->refresh();
        $this->assertSame('completed', $order->status);
    }

    public function test_cannot_reject_an_order_that_is_already_confirmed(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'confirmed']);
        $order->refresh();

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'rejected']);
        $order->refresh();

        $this->assertSame('confirmed', $order->status);
    }

    public function test_cannot_cancel_an_order_that_is_ready(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        foreach (['confirmed', 'preparing', 'ready'] as $status) {
            $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => $status]);
            $order->refresh();
        }

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);
        $order->refresh();

        $this->assertSame('ready', $order->status);
    }

    public function test_kitchen_role_can_update_order_status(): void
    {
        $kitchen = $this->userWithRole('kitchen');
        $order = $this->placeOrder();

        $this->actingAs($kitchen)->patch(route('admin.orders.status', $order), ['status' => 'confirmed'])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('confirmed', $order->fresh()->status);
    }
}
