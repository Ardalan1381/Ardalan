<?php

namespace Tests\Feature;

use App\Actions\CreateStaffOrderAction;
use App\Actions\TransitionOrderStatusAction;
use App\Jobs\PrintDocumentJob;
use App\Models\Category;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintRoute;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use App\Services\Printing\Connectors\NetworkPrintConnector;
use App\Services\Printing\EscPosPrinterService;
use App\Services\Printing\PrintRoutingService;
use App\Services\Printing\ReceiptFormatter;
use App\Support\Printing\EscPosDocumentBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrintingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function waiter(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'waiter')->first()->id]);
    }

    // ---- EscPosDocumentBuilder (pure byte-level) ----

    public function test_init_emits_the_esc_at_reset_command(): void
    {
        $bytes = (new EscPosDocumentBuilder)->init()->toBytes();

        $this->assertSame("\x1B\x40", $bytes);
    }

    public function test_cut_emits_the_gs_v_full_cut_command(): void
    {
        $bytes = (new EscPosDocumentBuilder)->cut()->toBytes();

        $this->assertSame("\x1D\x56\x00", $bytes);
    }

    public function test_text_is_passed_through_unmodified_outside_farsi_mode(): void
    {
        $bytes = (new EscPosDocumentBuilder)->text('Hello')->toBytes();

        $this->assertSame("Hello\n", $bytes);
    }

    public function test_select_farsi_codepage_emits_esc_t_n_and_transliterates_subsequent_text(): void
    {
        $builder = (new EscPosDocumentBuilder)->selectFarsiCodepage(22);
        $header = $builder->toBytes();

        $this->assertSame("\x1B\x74".chr(22), $header);

        $withText = $builder->text('سلام')->toBytes();
        $cp1256 = @iconv('UTF-8', 'CP1256//TRANSLIT', 'سلام');

        $this->assertSame($header.$cp1256."\n", $withText);
    }

    public function test_two_column_line_pads_between_label_and_value(): void
    {
        $bytes = (new EscPosDocumentBuilder)->twoColumnLine('Total', '100', 10)->toBytes();

        $this->assertSame("Total  100\n", $bytes);
    }

    // ---- NetworkPrintConnector (real local socket) ----

    public function test_network_connector_sends_exact_bytes_over_a_real_socket(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, "Could not start test socket server: {$errstr}");
        $address = stream_socket_get_name($server, false);
        [$host, $port] = explode(':', $address);

        $connector = new NetworkPrintConnector($host, (int) $port, 2.0);
        $connector->send("\x1B\x40Hello\n");

        $client = @stream_socket_accept($server, 2.0);
        $received = $client ? fread($client, 1024) : null;

        fclose($server);
        if ($client) {
            fclose($client);
        }

        $this->assertSame("\x1B\x40Hello\n", $received);
    }

    public function test_network_connector_throws_when_it_cannot_connect(): void
    {
        // Port 1 is a reserved/typically-closed port — connecting should fail fast.
        $connector = new NetworkPrintConnector('127.0.0.1', 1, 0.5);

        $this->expectException(\RuntimeException::class);
        $connector->send('test');
    }

    // ---- PrintRoutingService ----

    private function dineInOrderWithCategories(): array
    {
        $waiter = $this->waiter();
        $table = Table::factory()->create();
        $foodCategory = Category::factory()->create(['name' => 'Food']);
        $drinkCategory = Category::factory()->create(['name' => 'Drinks']);
        $foodProduct = Product::factory()->create(['category_id' => $foodCategory->id]);
        $drinkProduct = Product::factory()->create(['category_id' => $drinkCategory->id]);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $foodProduct->id, 'quantity' => 1],
                ['product_id' => $drinkProduct->id, 'quantity' => 1],
            ],
        ], $waiter);

        return [$order, $foodCategory, $drinkCategory];
    }

    public function test_category_specific_routes_queue_one_job_per_matched_category(): void
    {
        [$order, $foodCategory, $drinkCategory] = $this->dineInOrderWithCategories();
        $kitchenPrinter = Printer::create(['name' => 'Kitchen', 'connection_type' => 'network', 'ip_address' => '10.0.0.1', 'paper_width' => '80mm']);
        $barPrinter = Printer::create(['name' => 'Bar', 'connection_type' => 'network', 'ip_address' => '10.0.0.2', 'paper_width' => '80mm']);
        PrintRoute::create(['document_type' => 'kitchen_docket', 'printer_id' => $kitchenPrinter->id, 'category_id' => $foodCategory->id]);
        PrintRoute::create(['document_type' => 'bar_docket', 'printer_id' => $barPrinter->id, 'category_id' => $drinkCategory->id]);

        Queue::fake();
        $jobs = app(PrintRoutingService::class)->queueDocketJobsForOrder($order);

        $this->assertCount(2, $jobs);
        $this->assertSame(2, PrintJob::count());
        Queue::assertPushed(PrintDocumentJob::class, 2);

        $kitchenJob = PrintJob::where('document_type', 'kitchen_docket')->sole();
        $this->assertSame($foodCategory->id, $kitchenJob->category_id);
        $this->assertSame($kitchenPrinter->id, $kitchenJob->printer_id);
    }

    public function test_catch_all_route_prints_the_whole_order_when_no_category_specific_route_exists(): void
    {
        [$order] = $this->dineInOrderWithCategories();
        $printer = Printer::create(['name' => 'Single Printer', 'connection_type' => 'network', 'ip_address' => '10.0.0.1', 'paper_width' => '80mm']);
        PrintRoute::create(['document_type' => 'kitchen_docket', 'printer_id' => $printer->id, 'category_id' => null]);

        Queue::fake();
        $jobs = app(PrintRoutingService::class)->queueDocketJobsForOrder($order);

        $this->assertCount(1, $jobs);
        $this->assertNull($jobs[0]->category_id);
        $this->assertSame($printer->id, $jobs[0]->printer_id);
    }

    public function test_no_jobs_are_queued_when_no_printers_are_configured(): void
    {
        [$order] = $this->dineInOrderWithCategories();

        Queue::fake();
        $jobs = app(PrintRoutingService::class)->queueDocketJobsForOrder($order);

        $this->assertCount(0, $jobs);
        $this->assertSame(0, PrintJob::count());
    }

    public function test_confirming_an_order_dispatches_docket_print_jobs(): void
    {
        [$order] = $this->dineInOrderWithCategories();
        $printer = Printer::create(['name' => 'Kitchen', 'connection_type' => 'network', 'ip_address' => '10.0.0.1', 'paper_width' => '80mm']);
        PrintRoute::create(['document_type' => 'kitchen_docket', 'printer_id' => $printer->id, 'category_id' => null]);

        Queue::fake();
        app(TransitionOrderStatusAction::class)->execute($order->fresh(), 'confirmed', $this->waiter());

        Queue::assertPushed(PrintDocumentJob::class, 1);
    }

    // ---- PrintDocumentJob ----

    public function test_print_document_job_marks_itself_sent_on_success(): void
    {
        [$order] = $this->dineInOrderWithCategories();
        $order->loadMissing('items.product', 'items.options', 'table');

        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);
        [$host, $port] = explode(':', $address);
        $printer = Printer::create(['name' => 'Kitchen', 'connection_type' => 'network', 'ip_address' => $host, 'port' => (int) $port, 'paper_width' => '80mm']);

        $printJob = PrintJob::create([
            'printable_type' => Order::class,
            'printable_id' => $order->id,
            'document_type' => 'kitchen_docket',
            'category_id' => null,
            'printer_id' => $printer->id,
            'status' => 'queued',
        ]);

        // Accept the connection in a tiny async-ish way: fork isn't
        // available portably in PHPUnit, so drain after the job connects
        // by accepting once the job's socket handshake completes — the
        // job's own fwrite completes synchronously against the backlog
        // even before accept(), since TCP buffers the write.
        app(PrintDocumentJob::class, ['printJob' => $printJob])->handle(
            app(ReceiptFormatter::class),
            app(EscPosPrinterService::class),
        );

        $client = @stream_socket_accept($server, 2.0);
        fclose($server);
        if ($client) {
            fclose($client);
        }

        $this->assertSame('sent', $printJob->fresh()->status);
        $this->assertSame(1, $printJob->fresh()->attempts);
    }

    public function test_print_document_job_marks_itself_failed_and_rethrows_when_the_printer_is_unreachable(): void
    {
        [$order] = $this->dineInOrderWithCategories();
        $order->loadMissing('items.product', 'items.options', 'table');

        $printer = Printer::create(['name' => 'Unreachable', 'connection_type' => 'network', 'ip_address' => '127.0.0.1', 'port' => 1, 'paper_width' => '80mm']);
        $printJob = PrintJob::create([
            'printable_type' => Order::class,
            'printable_id' => $order->id,
            'document_type' => 'kitchen_docket',
            'category_id' => null,
            'printer_id' => $printer->id,
            'status' => 'queued',
        ]);

        $job = app(PrintDocumentJob::class, ['printJob' => $printJob]);

        try {
            $job->handle(app(ReceiptFormatter::class), app(EscPosPrinterService::class));
            $this->fail('Expected the job to throw when the printer is unreachable.');
        } catch (\RuntimeException) {
            // expected — Laravel's queue worker catches this for retry/backoff.
        }

        $this->assertSame('failed', $printJob->fresh()->status);
        $this->assertNotNull($printJob->fresh()->error_message);
        $this->assertSame(1, $printJob->fresh()->attempts);
    }

    public function test_on_the_sync_queue_an_unreachable_printer_does_not_fail_the_request(): void
    {
        [$order] = $this->dineInOrderWithCategories();

        $printer = Printer::create(['name' => 'Unreachable', 'connection_type' => 'network', 'ip_address' => '127.0.0.1', 'port' => 1, 'paper_width' => '80mm']);
        $printJob = PrintJob::create([
            'printable_type' => Order::class,
            'printable_id' => $order->id,
            'document_type' => 'kitchen_docket',
            'category_id' => null,
            'printer_id' => $printer->id,
            'status' => 'queued',
        ]);

        // Runs on the 'sync' connection, as in a request without a worker.
        PrintDocumentJob::dispatchSync($printJob);

        $this->assertSame('failed', $printJob->fresh()->status);
        $this->assertNotNull($printJob->fresh()->error_message);
    }

    // ---- Permissions ----

    public function test_only_owner_and_manager_have_printers_manage_permission(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
        $manager = User::factory()->create(['role_id' => Role::where('name', 'manager')->first()->id]);
        $waiter = $this->waiter();

        $this->assertTrue($owner->hasPermission('printers.manage'));
        $this->assertTrue($manager->hasPermission('printers.manage'));
        $this->assertFalse($waiter->hasPermission('printers.manage'));
    }
}
