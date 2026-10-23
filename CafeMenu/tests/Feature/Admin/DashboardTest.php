<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function placeOrder(int $basePrice = 5000): void
    {
        $product = Product::factory()->create(['base_price' => $basePrice]);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
    }

    public function test_owner_can_view_dashboard_with_metrics(): void
    {
        $owner = $this->userWithRole('owner');
        $this->placeOrder(5000);

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('todaySales', 5000);
        $response->assertViewHas('todayOrderCount', 1);
        $response->assertViewHas('todayAverageOrderValue', 5000);
        $response->assertViewHas('activeOrdersCount', 1);
    }

    public function test_kitchen_cannot_access_dashboard_directly(): void
    {
        $kitchen = $this->userWithRole('kitchen');

        $this->actingAs($kitchen)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_kitchen_login_redirects_to_orders_not_dashboard(): void
    {
        $kitchen = $this->userWithRole('kitchen');

        $response = $this->post('/login', [
            'email' => $kitchen->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.orders.index'));
    }

    public function test_owner_login_redirects_to_dashboard(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->post('/login', [
            'email' => $owner->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_cancelled_orders_are_excluded_from_sales_totals(): void
    {
        $owner = $this->userWithRole('owner');
        $this->placeOrder(5000);
        $order = Order::latest('id')->firstOrFail();
        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertViewHas('todaySales', 0);
    }
}
