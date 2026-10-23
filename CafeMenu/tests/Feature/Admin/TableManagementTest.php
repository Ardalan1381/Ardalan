<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Table;
use App\Models\TableSection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableManagementTest extends TestCase
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

    public function test_owner_can_view_tables_pages(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();

        $this->actingAs($owner)->get(route('admin.tables.index'))->assertOk()->assertSee($table->name);
        $this->actingAs($owner)->get(route('admin.tables.create'))->assertOk();
        $this->actingAs($owner)->get(route('admin.tables.edit', $table))->assertOk();
    }

    public function test_owner_can_create_a_table(): void
    {
        $owner = $this->userWithRole('owner');
        $section = TableSection::factory()->create();

        $response = $this->actingAs($owner)->post(route('admin.tables.store'), [
            'table_section_id' => $section->id,
            'name' => 'Table 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.tables.index'));
        $this->assertDatabaseHas('tables', ['name' => 'Table 1', 'table_section_id' => $section->id]);
    }

    public function test_table_requires_an_existing_section(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.tables.store'), [
            'table_section_id' => 999999,
            'name' => 'Table 1',
            'status' => 'available',
        ]);

        $response->assertSessionHasErrors('table_section_id');
    }

    public function test_table_name_must_be_unique_within_the_same_section(): void
    {
        $owner = $this->userWithRole('owner');
        $section = TableSection::factory()->create();
        Table::factory()->create(['table_section_id' => $section->id, 'name' => 'Table 1']);

        $response = $this->actingAs($owner)->post(route('admin.tables.store'), [
            'table_section_id' => $section->id,
            'name' => 'Table 1',
            'status' => 'available',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_table_name_can_repeat_across_different_sections(): void
    {
        $owner = $this->userWithRole('owner');
        $sectionA = TableSection::factory()->create();
        $sectionB = TableSection::factory()->create();
        Table::factory()->create(['table_section_id' => $sectionA->id, 'name' => 'Table 1']);

        $response = $this->actingAs($owner)->post(route('admin.tables.store'), [
            'table_section_id' => $sectionB->id,
            'name' => 'Table 1',
            'status' => 'available',
        ]);

        $response->assertRedirect(route('admin.tables.index'));
        $this->assertDatabaseHas('tables', ['table_section_id' => $sectionB->id, 'name' => 'Table 1']);
    }

    public function test_table_receives_a_unique_random_qr_token_on_creation(): void
    {
        $section = TableSection::factory()->create();
        $tableA = Table::factory()->create(['table_section_id' => $section->id]);
        $tableB = Table::factory()->create(['table_section_id' => $section->id]);

        $this->assertNotEmpty($tableA->qr_token);
        $this->assertNotEmpty($tableB->qr_token);
        $this->assertNotSame($tableA->qr_token, $tableB->qr_token);
        $this->assertSame(32, strlen($tableA->qr_token));
    }

    public function test_qr_token_cannot_be_set_by_the_client(): void
    {
        $owner = $this->userWithRole('owner');
        $section = TableSection::factory()->create();

        $this->actingAs($owner)->post(route('admin.tables.store'), [
            'table_section_id' => $section->id,
            'name' => 'Table 1',
            'status' => 'available',
            'qr_token' => 'attacker-chosen-token',
        ]);

        $table = Table::where('name', 'Table 1')->firstOrFail();
        $this->assertNotSame('attacker-chosen-token', $table->qr_token);
    }

    public function test_waiter_cannot_create_tables(): void
    {
        $waiter = $this->userWithRole('waiter');
        $section = TableSection::factory()->create();

        $response = $this->actingAs($waiter)->post(route('admin.tables.store'), [
            'table_section_id' => $section->id,
            'name' => 'Table 1',
            'status' => 'available',
        ]);

        $response->assertForbidden();
    }

    public function test_waiter_cannot_directly_access_table_edit_page_idor(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();

        $this->actingAs($waiter)
            ->get(route('admin.tables.edit', $table))
            ->assertForbidden();
    }
}
