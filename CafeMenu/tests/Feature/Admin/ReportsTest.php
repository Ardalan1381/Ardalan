<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
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

    private function placeTakeawayOrder(int $basePrice = 5000): void
    {
        $product = Product::factory()->create(['base_price' => $basePrice, 'name' => 'Espresso']);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
    }

    public function test_owner_can_view_reports_page(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.reports.index'))->assertOk();
    }

    public function test_waiter_cannot_view_reports(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_reports_shows_sales_by_period(): void
    {
        $owner = $this->userWithRole('owner');
        $this->placeTakeawayOrder(5000);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));

        $response->assertOk();
        $salesByPeriod = $response->viewData('salesByPeriod');
        $this->assertCount(1, $salesByPeriod);
        $this->assertEquals(5000, $salesByPeriod->first()->total);
    }

    public function test_reports_shows_product_sales(): void
    {
        $owner = $this->userWithRole('owner');
        $this->placeTakeawayOrder(5000);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));

        $products = $response->viewData('popularProducts');
        $this->assertSame('Espresso', $products->first()->product_name_snapshot);
        $this->assertEquals(1, $products->first()->total_quantity);
    }

    public function test_reports_shows_table_sales_for_dine_in_orders_only(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 4000]);

        $this->get('/t/'.$table->qr_token);
        $this->postJson(route('checkout.store'), [
            'type' => 'dine_in',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->placeTakeawayOrder(5000);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));

        $tableSales = $response->viewData('tableSales');
        $this->assertCount(1, $tableSales);
        $this->assertSame($table->name, $tableSales->first()->table_name);
        $this->assertEquals(4000, $tableSales->first()->total);
    }

    public function test_reports_shows_payment_summary(): void
    {
        $cashier = $this->userWithRole('cashier');
        $this->placeTakeawayOrder(5000);
        $order = Order::latest('id')->firstOrFail();

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 5000, 'status' => 'paid',
        ]);

        $response = $this->actingAs($cashier)->get(route('admin.reports.index'));
        $response->assertForbidden(); // cashier lacks reports.view

        $owner = $this->userWithRole('owner');
        $response = $this->actingAs($owner)->get(route('admin.reports.index'));
        $summary = $response->viewData('paymentSummary');

        $this->assertSame('cash', $summary->first()->method);
        $this->assertSame('paid', $summary->first()->status);
        $this->assertEquals(5000, $summary->first()->total);
    }

    public function test_reports_respects_date_range_filter(): void
    {
        $owner = $this->userWithRole('owner');
        $this->placeTakeawayOrder(5000);

        $response = $this->actingAs($owner)->get(route('admin.reports.index', [
            'from' => now()->addDays(10)->toDateString(),
            'to' => now()->addDays(11)->toDateString(),
        ]));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('salesByPeriod'));
    }
}
