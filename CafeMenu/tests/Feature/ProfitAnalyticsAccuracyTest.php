<?php

namespace Tests\Feature;

use App\Actions\ProcessRefundAction;
use App\Actions\RecordPaymentAction;
use App\Actions\TransitionOrderStatusAction;
use App\Models\BusinessSetting;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Services\Reports\ProfitAnalyticsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AUD-10: profit uses the cost actually consumed (historical), counts only
 * completed orders as realised sales, deducts completed refunds, and never
 * uses floating point for money.
 */
class ProfitAnalyticsAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
    }

    private function order(Product $product, string $finalStatus): Order
    {
        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway', 'customer_name' => 'J', 'customer_phone' => '1',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();
        $order = Order::latest('id')->firstOrFail();

        foreach (['confirmed', 'preparing', 'ready', 'completed'] as $status) {
            if ($finalStatus === 'pending') {
                break;
            }
            app(TransitionOrderStatusAction::class)->execute($order->fresh(), $status, $this->owner);
            if ($status === $finalStatus) {
                break;
            }
        }

        return $order->fresh();
    }

    public function test_profit_uses_historical_cost_and_only_completed_orders(): void
    {
        $gram = InventoryUnit::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'mass', 'base_factor' => 1]);
        $coffee = InventoryItem::create(['name' => 'Coffee', 'base_unit_id' => $gram->id, 'is_active' => true]);
        $stock = app(StockMovementService::class);
        $stock->record($coffee, 'purchase_receipt', '1000', unitCost: 10);

        $latte = Product::factory()->create(['name' => 'Latte', 'base_price' => 5000]);
        $latte->recipeItems()->create(['inventory_item_id' => $coffee->id, 'quantity' => '18', 'unit_id' => $gram->id]);

        $completed = $this->order($latte, 'completed');   // consumed at 10/g
        $this->order($latte, 'pending');                   // not a realised sale
        $this->order($latte, 'preparing');                 // not a realised sale yet

        // Afterwards the cost of coffee rises and the recipe is changed.
        $stock->record($coffee, 'purchase_receipt', '1000', unitCost: 50);
        $latte->recipeItems()->update(['quantity' => '30']);

        $rows = app(ProfitAnalyticsService::class)->profitByProduct(now()->subDay(), now()->addDay());

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(1, $row->quantity);
        $this->assertSame(5000, $row->revenue);
        $this->assertSame(180, $row->cogs);           // 18g × 10, not 30g × current cost
        $this->assertSame(4820, $row->profit);
        $this->assertFalse($row->cogs_estimated);

        $this->assertSame($completed->id, Order::where('status', 'completed')->value('id'));
    }

    public function test_summary_deducts_completed_refunds_but_not_pending_ones(): void
    {
        $product = Product::factory()->create(['base_price' => 10000]);
        $order = $this->order($product, 'completed');
        $card = app(RecordPaymentAction::class)->execute($order, ['method' => 'card', 'amount' => 10000, 'status' => 'paid'], $this->owner);
        app(ProcessRefundAction::class)->execute($card, ['amount' => 3000, 'refund_method' => 'cash'], $this->owner);
        app(ProcessRefundAction::class)->execute($card, ['amount' => 2000], $this->owner); // pending card reversal

        $summary = app(ProfitAnalyticsService::class)->summary(now()->subDay(), now()->addDay());

        $this->assertSame(10000, $summary['revenue']);
        $this->assertSame(3000, $summary['refunds']);
        $this->assertSame(7000, $summary['net_revenue']);
        $this->assertSame(2000, $summary['pending_refunds']);
    }

    public function test_report_period_boundaries_follow_the_business_timezone(): void
    {
        BusinessSetting::current()->update(['timezone' => 'Asia/Tehran']);
        $product = Product::factory()->create(['base_price' => 1000]);

        // 22:00 UTC on Oct 22 is 01:30 on Oct 23 in Tehran.
        Carbon::setTestNow(Carbon::parse('2026-10-22 22:00:00', 'UTC'));
        $this->order($product, 'completed');
        Carbon::setTestNow();

        $html = $this->actingAs($this->owner)
            ->get(route('admin.reports.index', ['from' => '2026-10-23', 'to' => '2026-10-23']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-profit-quantity="1"', $html);
    }
}
