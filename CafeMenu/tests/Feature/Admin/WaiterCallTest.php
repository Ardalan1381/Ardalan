<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use App\Models\WaiterCall;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaiterCallTest extends TestCase
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

    public function test_waiter_can_view_and_resolve_a_call(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $waiterCall = WaiterCall::create(['table_id' => $table->id, 'status' => 'pending']);

        $this->actingAs($waiter)->get(route('admin.waiter-calls.index'))->assertOk()->assertSee($table->name);

        $response = $this->actingAs($waiter)->patch(route('admin.waiter-calls.resolve', $waiterCall));

        $response->assertRedirect(route('admin.waiter-calls.index'));
        $waiterCall->refresh();
        $this->assertSame('resolved', $waiterCall->status);
        $this->assertSame($waiter->id, $waiterCall->resolved_by_user_id);
        $this->assertNotNull($waiterCall->resolved_at);
    }

    public function test_resolving_an_already_resolved_call_is_rejected(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $waiterCall = WaiterCall::create([
            'table_id' => $table->id,
            'status' => 'resolved',
            'resolved_by_user_id' => $waiter->id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($waiter)->patch(route('admin.waiter-calls.resolve', $waiterCall));

        $this->assertSame($waiter->id, $waiterCall->fresh()->resolved_by_user_id);
    }

    public function test_ajax_resolve_returns_json_without_redirecting(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $waiterCall = WaiterCall::create(['table_id' => $table->id, 'status' => 'pending']);

        $response = $this->actingAs($waiter)->patchJson(route('admin.waiter-calls.resolve', $waiterCall));

        $response->assertOk()->assertJson([
            'status' => 'resolved',
            'resolved_by' => $waiter->name,
        ]);
        $this->assertSame('resolved', $waiterCall->fresh()->status);
    }

    public function test_ajax_resolve_of_an_already_resolved_call_returns_422_with_message(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();
        $waiterCall = WaiterCall::create([
            'table_id' => $table->id,
            'status' => 'resolved',
            'resolved_by_user_id' => $owner->id,
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($owner)->patchJson(route('admin.waiter-calls.resolve', $waiterCall));

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_kitchen_role_cannot_view_or_resolve_waiter_calls(): void
    {
        $kitchen = $this->userWithRole('kitchen');
        $table = Table::factory()->create();
        $waiterCall = WaiterCall::create(['table_id' => $table->id, 'status' => 'pending']);

        $this->actingAs($kitchen)->get(route('admin.waiter-calls.index'))->assertForbidden();
        $this->actingAs($kitchen)->patch(route('admin.waiter-calls.resolve', $waiterCall))->assertForbidden();
    }

    public function test_audit_log_records_resolution(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();
        $waiterCall = WaiterCall::create(['table_id' => $table->id, 'status' => 'pending']);

        $this->actingAs($owner)->patch(route('admin.waiter-calls.resolve', $waiterCall));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'waiter_call.resolved',
            'auditable_type' => (new WaiterCall)->getMorphClass(),
            'auditable_id' => $waiterCall->id,
        ]);
    }
}
