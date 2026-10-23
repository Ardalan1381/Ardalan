<?php

namespace Tests\Feature;

use App\Actions\CreateStaffOrderAction;
use App\Actions\Invoices\GenerateInvoiceAction;
use App\Actions\Invoices\SetDefaultInvoiceTemplateAction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function waiter(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'waiter')->first()->id, 'name' => 'Waiter Wendy']);
    }

    public function test_generating_an_invoice_snapshots_order_customer_table_and_waiter(): void
    {
        $waiter = $this->waiter();
        $table = Table::factory()->create(['name' => 'T-12']);
        $customer = Customer::create(['name' => 'Ali Rezaei', 'phone' => '0912', 'status' => 'active']);
        $product = \App\Models\Product::factory()->create(['base_price' => 5000, 'name' => 'Latte']);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $waiter);

        $invoice = app(GenerateInvoiceAction::class)->execute($order, $waiter);

        $this->assertNotEmpty($invoice->invoice_number);
        $this->assertSame('Ali Rezaei', $invoice->customer_name_snapshot);
        $this->assertSame('0912', $invoice->customer_phone_snapshot);
        $this->assertSame('T-12', $invoice->table_name_snapshot);
        $this->assertSame('Waiter Wendy', $invoice->waiter_name_snapshot);
        $this->assertSame($order->grand_total, $invoice->grand_total);
        $this->assertCount(1, $invoice->lineItems);
        $this->assertSame('Latte', $invoice->lineItems->first()->product_name_snapshot);
        $this->assertSame(2, $invoice->lineItems->first()->quantity);
    }

    public function test_generating_twice_returns_the_same_invoice(): void
    {
        $waiter = $this->waiter();
        $table = Table::factory()->create();
        $product = \App\Models\Product::factory()->create();

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        $first = app(GenerateInvoiceAction::class)->execute($order, $waiter);
        $second = app(GenerateInvoiceAction::class)->execute($order, $waiter);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::count());
    }

    public function test_invoice_survives_the_customers_name_changing_afterward(): void
    {
        $waiter = $this->waiter();
        $table = Table::factory()->create();
        $customer = Customer::create(['name' => 'Original Name', 'status' => 'active']);
        $product = \App\Models\Product::factory()->create();

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        $invoice = app(GenerateInvoiceAction::class)->execute($order, $waiter);
        $customer->update(['name' => 'Changed Name']);

        $this->assertSame('Original Name', $invoice->fresh()->customer_name_snapshot);
    }

    public function test_setting_a_default_template_unsets_any_previous_default(): void
    {
        $a = InvoiceTemplate::create(['name' => 'A4', 'paper_size' => 'a4', 'is_default' => true]);
        $b = InvoiceTemplate::create(['name' => 'Thermal', 'paper_size' => 'thermal_80mm']);

        app(SetDefaultInvoiceTemplateAction::class)->execute($b);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
    }

    public function test_generated_invoice_uses_the_default_template(): void
    {
        $template = InvoiceTemplate::create(['name' => 'Default', 'paper_size' => 'a4']);
        app(SetDefaultInvoiceTemplateAction::class)->execute($template);

        $waiter = $this->waiter();
        $table = Table::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        $invoice = app(GenerateInvoiceAction::class)->execute($order, $waiter);

        $this->assertSame($template->id, $invoice->template_id);
    }
}
