<?php

namespace Tests\Feature;

use App\Models\Table;
use App\Models\WaiterCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaiterCallTest extends TestCase
{
    use RefreshDatabase;

    public function test_calling_a_waiter_at_a_scanned_table_creates_a_pending_call(): void
    {
        $table = Table::factory()->create();

        $this->get('/t/'.$table->qr_token);

        $response = $this->postJson(route('waiter-call.store'));

        $response->assertOk();
        $this->assertDatabaseHas('waiter_calls', ['table_id' => $table->id, 'status' => 'pending']);
    }

    public function test_calling_a_waiter_twice_reuses_the_existing_pending_call(): void
    {
        $table = Table::factory()->create();
        $this->get('/t/'.$table->qr_token);

        $this->postJson(route('waiter-call.store'))->assertOk();
        $this->postJson(route('waiter-call.store'))->assertOk();

        $this->assertSame(1, WaiterCall::where('table_id', $table->id)->count());
    }

    public function test_calling_a_waiter_without_a_scanned_table_fails(): void
    {
        $response = $this->postJson(route('waiter-call.store'));

        $response->assertUnprocessable()->assertJsonValidationErrors('table');
        $this->assertSame(0, WaiterCall::count());
    }

    public function test_calling_a_waiter_for_an_inactive_table_fails(): void
    {
        $table = Table::factory()->create();
        $this->get('/t/'.$table->qr_token);

        $table->update(['is_active' => false]);

        $this->postJson(route('waiter-call.store'))->assertUnprocessable();
        $this->assertSame(0, WaiterCall::count());
    }
}
