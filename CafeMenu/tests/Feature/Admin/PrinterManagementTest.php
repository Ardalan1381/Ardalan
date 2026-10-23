<?php

namespace Tests\Feature\Admin;

use App\Actions\CreateStaffOrderAction;
use App\Jobs\PrintDocumentJob;
use App\Models\Category;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrinterManagementTest extends TestCase
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

    public function test_owner_can_create_edit_and_delete_a_printer(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->post(route('admin.printers.store'), [
            'name' => 'Front Counter',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.50',
            'port' => 9100,
            'paper_width' => '80mm',
            'is_active' => '1',
        ])->assertRedirect(route('admin.printers.index'));

        $printer = Printer::where('name', 'Front Counter')->firstOrFail();

        $this->actingAs($owner)->put(route('admin.printers.update', $printer), [
            'name' => 'Front Counter Updated',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.51',
            'port' => 9100,
            'paper_width' => '58mm',
            'is_active' => '1',
        ])->assertRedirect(route('admin.printers.index'));

        $this->assertSame('Front Counter Updated', $printer->fresh()->name);
        $this->assertSame('58mm', $printer->fresh()->paper_width);

        $this->actingAs($owner)->delete(route('admin.printers.destroy', $printer))
            ->assertRedirect(route('admin.printers.index'));

        $this->assertModelMissing($printer);
    }

    public function test_usb_printer_requires_a_device_path(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.printers.store'), [
            'name' => 'Kitchen USB',
            'connection_type' => 'usb',
            'paper_width' => '80mm',
        ]);

        $response->assertSessionHasErrors('device_path');
    }

    public function test_cannot_delete_a_printer_that_has_a_print_route(): void
    {
        $owner = $this->userWithRole('owner');
        $printer = Printer::create(['name' => 'Kitchen', 'connection_type' => 'network', 'ip_address' => '10.0.0.1', 'paper_width' => '80mm']);
        PrintRoute::create(['document_type' => 'kitchen_docket', 'printer_id' => $printer->id, 'category_id' => null]);

        $response = $this->actingAs($owner)->delete(route('admin.printers.destroy', $printer));

        $response->assertRedirect(route('admin.printers.index'));
        $this->assertModelExists($printer);
    }

    public function test_waiter_without_printers_manage_is_forbidden(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->assertFalse($waiter->hasPermission('printers.manage'));

        $this->actingAs($waiter)->get(route('admin.printers.index'))->assertForbidden();
        $this->actingAs($waiter)->get(route('admin.printers.create'))->assertForbidden();
        $this->actingAs($waiter)->post(route('admin.printers.store'), [])->assertForbidden();
    }

    public function test_owner_can_create_and_delete_a_print_route(): void
    {
        $owner = $this->userWithRole('owner');
        $printer = Printer::create(['name' => 'Bar', 'connection_type' => 'network', 'ip_address' => '10.0.0.2', 'paper_width' => '80mm']);
        $category = Category::factory()->create();

        $this->actingAs($owner)->post(route('admin.print-routes.store'), [
            'document_type' => 'bar_docket',
            'printer_id' => $printer->id,
            'category_id' => $category->id,
            'is_active' => '1',
        ])->assertRedirect(route('admin.print-routes.index'));

        $printRoute = PrintRoute::where('printer_id', $printer->id)->firstOrFail();
        $this->assertSame('bar_docket', $printRoute->document_type);

        $this->actingAs($owner)->delete(route('admin.print-routes.destroy', $printRoute))
            ->assertRedirect(route('admin.print-routes.index'));

        $this->assertModelMissing($printRoute);
    }

    public function test_waiter_without_printers_manage_cannot_manage_print_routes(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.print-routes.index'))->assertForbidden();
    }

    private function orderWithOneItem(User $waiter): Order
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create();

        return app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);
    }

    public function test_triggering_a_receipt_print_creates_a_queued_print_job_and_dispatches_the_job(): void
    {
        $waiter = $this->userWithRole('waiter');
        $order = $this->orderWithOneItem($waiter);
        $printer = Printer::create(['name' => 'Receipt Printer', 'connection_type' => 'network', 'ip_address' => '10.0.0.5', 'paper_width' => '80mm', 'is_active' => true]);

        Queue::fake();

        $response = $this->actingAs($waiter)->post(route('admin.orders.print-receipt', $order), [
            'printer_id' => $printer->id,
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        $printJob = PrintJob::sole();
        $this->assertSame('receipt', $printJob->document_type);
        $this->assertSame('queued', $printJob->status);
        $this->assertSame($printer->id, $printJob->printer_id);
        $this->assertSame($order->id, $printJob->printable_id);
        $this->assertSame(Order::class, $printJob->printable_type);

        Queue::assertPushed(PrintDocumentJob::class, 1);
    }

    public function test_receipt_print_is_rejected_for_an_inactive_printer(): void
    {
        $waiter = $this->userWithRole('waiter');
        $order = $this->orderWithOneItem($waiter);
        $printer = Printer::create(['name' => 'Inactive', 'connection_type' => 'network', 'ip_address' => '10.0.0.6', 'paper_width' => '80mm', 'is_active' => false]);

        Queue::fake();

        $response = $this->actingAs($waiter)->post(route('admin.orders.print-receipt', $order), [
            'printer_id' => $printer->id,
        ]);

        $response->assertSessionHasErrors('printer_id');
        $this->assertSame(0, PrintJob::count());
        Queue::assertNotPushed(PrintDocumentJob::class);
    }
}
