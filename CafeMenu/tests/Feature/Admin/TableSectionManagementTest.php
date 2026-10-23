<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Table;
use App\Models\TableSection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableSectionManagementTest extends TestCase
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

    public function test_owner_can_view_and_manage_sections_pages(): void
    {
        $owner = $this->userWithRole('owner');
        $section = TableSection::factory()->create();

        $this->actingAs($owner)->get(route('admin.table-sections.index'))->assertOk()->assertSee($section->name);
        $this->actingAs($owner)->get(route('admin.table-sections.create'))->assertOk();
        $this->actingAs($owner)->get(route('admin.table-sections.edit', $section))->assertOk();
    }

    public function test_waiter_cannot_view_sections_list(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.table-sections.index'))->assertForbidden();
    }

    public function test_owner_can_create_a_section(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.table-sections.store'), [
            'name' => 'Patio',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.table-sections.index'));
        $this->assertDatabaseHas('table_sections', ['name' => 'Patio']);
    }

    public function test_section_name_must_be_unique(): void
    {
        $owner = $this->userWithRole('owner');
        TableSection::factory()->create(['name' => 'Patio']);

        $response = $this->actingAs($owner)->post(route('admin.table-sections.store'), [
            'name' => 'Patio',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_cannot_delete_a_section_that_still_has_tables(): void
    {
        $owner = $this->userWithRole('owner');
        $section = TableSection::factory()->create();
        Table::factory()->create(['table_section_id' => $section->id]);

        $response = $this->actingAs($owner)->delete(route('admin.table-sections.destroy', $section));

        $response->assertRedirect(route('admin.table-sections.index'));
        $this->assertModelExists($section);
    }

    public function test_can_delete_an_empty_section(): void
    {
        $owner = $this->userWithRole('owner');
        $section = TableSection::factory()->create();

        $this->actingAs($owner)->delete(route('admin.table-sections.destroy', $section));

        $this->assertModelMissing($section);
    }

    public function test_waiter_cannot_directly_access_section_edit_page_idor(): void
    {
        $waiter = $this->userWithRole('waiter');
        $section = TableSection::factory()->create();

        $this->actingAs($waiter)
            ->get(route('admin.table-sections.edit', $section))
            ->assertForbidden();
    }
}
