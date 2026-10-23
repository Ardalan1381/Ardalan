<?php

namespace Tests\Feature\Admin;

use App\Actions\CreateStaffOrderAction;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
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

    private function orderWithOneItem(User $waiter): Order
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000, 'name' => 'Latte']);

        return app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $waiter);
    }

    public function test_generating_an_invoice_through_http_is_idempotent(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->orderWithOneItem($owner);

        $first = $this->actingAs($owner)->post(route('admin.orders.invoice.generate', $order));
        $first->assertRedirect();

        $this->assertSame(1, Invoice::count());

        // Visiting/generating a second time must not create a duplicate.
        $second = $this->actingAs($owner)->post(route('admin.orders.invoice.generate', $order));
        $second->assertRedirect();

        $this->assertSame(1, Invoice::count());
    }

    public function test_generated_invoice_show_page_renders_snapshot_data(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->orderWithOneItem($owner);

        $this->actingAs($owner)->post(route('admin.orders.invoice.generate', $order));
        $invoice = Invoice::sole();

        $response = $this->actingAs($owner)->get(route('admin.invoices.show', $invoice));

        $response->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Latte');
    }

    public function test_cashier_can_view_but_not_generate_an_invoice(): void
    {
        $owner = $this->userWithRole('owner');
        $cashier = $this->userWithRole('cashier');
        $order = $this->orderWithOneItem($owner);

        $this->assertTrue($cashier->hasPermission('invoices.view'));
        $this->assertFalse($cashier->hasPermission('invoices.manage'));

        $this->actingAs($cashier)->post(route('admin.orders.invoice.generate', $order))->assertForbidden();

        $this->actingAs($owner)->post(route('admin.orders.invoice.generate', $order));
        $invoice = Invoice::sole();

        $this->actingAs($cashier)->get(route('admin.invoices.show', $invoice))->assertOk();
    }

    public function test_order_show_page_offers_to_generate_an_invoice_when_none_exists(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->orderWithOneItem($owner);

        $this->actingAs($owner)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(route('admin.orders.invoice.generate', $order));
    }

    public function test_owner_can_manage_invoice_templates(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->post(route('admin.invoice-templates.store'), [
            'name' => 'Thermal receipt',
            'paper_size' => 'thermal_80mm',
            'show_logo' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.invoice-templates.index'));

        $template = InvoiceTemplate::where('name', 'Thermal receipt')->firstOrFail();

        $this->actingAs($owner)->patch(route('admin.invoice-templates.make-default', $template))
            ->assertRedirect(route('admin.invoice-templates.index'));

        $this->assertTrue($template->fresh()->is_default);
    }

    public function test_cannot_delete_an_invoice_template_already_used_by_an_invoice(): void
    {
        $owner = $this->userWithRole('owner');
        $template = InvoiceTemplate::create(['name' => 'A4', 'paper_size' => 'a4', 'is_default' => true]);
        $order = $this->orderWithOneItem($owner);
        $this->actingAs($owner)->post(route('admin.orders.invoice.generate', $order));

        $response = $this->actingAs($owner)->delete(route('admin.invoice-templates.destroy', $template));

        $response->assertRedirect(route('admin.invoice-templates.index'));
        $this->assertModelExists($template);
    }

    public function test_waiter_without_invoices_manage_cannot_manage_templates(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.invoice-templates.index'))->assertForbidden();
    }
}
