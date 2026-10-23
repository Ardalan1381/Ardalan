<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
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

    public function test_owner_can_view_categories_list(): void
    {
        $owner = $this->userWithRole('owner');
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($owner)->get(route('admin.categories.index'))->assertOk()->assertSee($parent->name);
        $this->actingAs($owner)->get(route('admin.categories.create'))->assertOk();
        $this->actingAs($owner)->get(route('admin.categories.edit', $parent))->assertOk();
    }

    public function test_waiter_cannot_view_categories_list(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.categories.index'))->assertForbidden();
    }

    public function test_owner_can_create_a_top_level_category(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.categories.store'), [
            'name' => 'Beverages',
            'sort_order' => 0,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Beverages', 'slug' => 'beverages', 'parent_id' => null]);
    }

    public function test_owner_can_create_a_nested_category(): void
    {
        $owner = $this->userWithRole('owner');
        $parent = Category::factory()->create();

        $response = $this->actingAs($owner)->post(route('admin.categories.store'), [
            'name' => 'Hot Coffee',
            'parent_id' => $parent->id,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Hot Coffee', 'parent_id' => $parent->id]);
    }

    public function test_category_slug_must_be_unique(): void
    {
        $owner = $this->userWithRole('owner');
        Category::factory()->create(['slug' => 'beverages']);

        $response = $this->actingAs($owner)->post(route('admin.categories.store'), [
            'name' => 'Beverages',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_category_cannot_be_set_as_its_own_parent(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();

        $response = $this->actingAs($owner)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'parent_id' => $category->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_circular_category_hierarchy_is_rejected(): void
    {
        $owner = $this->userWithRole('owner');
        $grandparent = Category::factory()->create();
        $parent = Category::factory()->create(['parent_id' => $grandparent->id]);

        $response = $this->actingAs($owner)->put(route('admin.categories.update', $grandparent), [
            'name' => $grandparent->name,
            'parent_id' => $parent->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $owner = $this->userWithRole('owner');
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $response = $this->actingAs($owner)->delete(route('admin.categories.destroy', $parent));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertModelExists($parent);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($owner)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertModelExists($category);
    }

    public function test_can_delete_empty_category(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();

        $this->actingAs($owner)->delete(route('admin.categories.destroy', $category));

        $this->assertModelMissing($category);
    }

    public function test_waiter_cannot_directly_access_category_edit_page_idor(): void
    {
        $waiter = $this->userWithRole('waiter');
        $category = Category::factory()->create();

        $this->actingAs($waiter)
            ->get(route('admin.categories.edit', $category))
            ->assertForbidden();
    }
}
