<?php

namespace Tests\Feature\Admin;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeEditorTest extends TestCase
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

    private function gramUnit(): InventoryUnit
    {
        return InventoryUnit::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'mass', 'base_factor' => 1]);
    }

    public function test_manager_can_set_a_products_recipe(): void
    {
        $manager = $this->userWithRole('manager');
        $product = Product::factory()->create();
        $gram = $this->gramUnit();
        $coffee = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $gram->id, 'is_active' => true]);
        $milk = InventoryItem::create(['name' => 'Milk', 'base_unit_id' => $gram->id, 'is_active' => true]);

        $response = $this->actingAs($manager)->put(route('admin.products.recipe.update', $product), [
            'lines' => [
                ['inventory_item_id' => $coffee->id, 'quantity' => '18', 'unit_id' => $gram->id],
                ['inventory_item_id' => $milk->id, 'quantity' => '200', 'unit_id' => $gram->id],
            ],
        ]);

        $response->assertRedirect(route('admin.products.edit', $product));
        $this->assertCount(2, $product->recipeItems);
    }

    public function test_resubmitting_a_recipe_replaces_lines_not_included(): void
    {
        $manager = $this->userWithRole('manager');
        $product = Product::factory()->create();
        $gram = $this->gramUnit();
        $coffee = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $gram->id, 'is_active' => true]);
        $milk = InventoryItem::create(['name' => 'Milk', 'base_unit_id' => $gram->id, 'is_active' => true]);

        $this->actingAs($manager)->put(route('admin.products.recipe.update', $product), [
            'lines' => [
                ['inventory_item_id' => $coffee->id, 'quantity' => '18', 'unit_id' => $gram->id],
                ['inventory_item_id' => $milk->id, 'quantity' => '200', 'unit_id' => $gram->id],
            ],
        ]);

        // Resubmit with only coffee — milk should be dropped, not accumulated.
        $this->actingAs($manager)->put(route('admin.products.recipe.update', $product), [
            'lines' => [
                ['inventory_item_id' => $coffee->id, 'quantity' => '20', 'unit_id' => $gram->id],
            ],
        ]);

        $product->refresh();
        $this->assertCount(1, $product->recipeItems);
        $this->assertSame('20.0000', $product->recipeItems->first()->quantity);
    }

    public function test_a_user_without_products_manage_cannot_edit_a_recipe(): void
    {
        $waiter = $this->userWithRole('waiter');
        $product = Product::factory()->create();
        $gram = $this->gramUnit();
        $item = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $gram->id, 'is_active' => true]);

        $this->actingAs($waiter)->put(route('admin.products.recipe.update', $product), [
            'lines' => [
                ['inventory_item_id' => $item->id, 'quantity' => '18', 'unit_id' => $gram->id],
            ],
        ])->assertForbidden();
    }

    public function test_manager_can_set_an_add_ons_recipe(): void
    {
        $manager = $this->userWithRole('manager');
        $product = Product::factory()->create();
        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Add-ons',
            'selection_type' => 'multiple',
            'min_select' => 0,
            'is_active' => true,
        ]);
        $option = $group->options()->create(['name' => 'Caramel Syrup', 'price_delta' => 500, 'is_active' => true]);
        $gram = $this->gramUnit();
        $syrup = InventoryItem::create(['name' => 'Caramel Syrup', 'base_unit_id' => $gram->id, 'is_active' => true]);

        $response = $this->actingAs($manager)->put(
            route('admin.products.option-groups.options.recipe.update', [$product, $group, $option]),
            ['lines' => [['inventory_item_id' => $syrup->id, 'quantity' => '10', 'unit_id' => $gram->id]]]
        );

        $response->assertRedirect(route('admin.products.edit', $product));
        $this->assertCount(1, $option->recipeItems);
    }

    public function test_submitting_an_empty_recipe_clears_all_lines(): void
    {
        $manager = $this->userWithRole('manager');
        $product = Product::factory()->create();
        $gram = $this->gramUnit();
        $coffee = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $gram->id, 'is_active' => true]);

        $this->actingAs($manager)->put(route('admin.products.recipe.update', $product), [
            'lines' => [['inventory_item_id' => $coffee->id, 'quantity' => '18', 'unit_id' => $gram->id]],
        ]);
        $this->assertCount(1, $product->fresh()->recipeItems);

        $this->actingAs($manager)->put(route('admin.products.recipe.update', $product), []);

        $this->assertCount(0, $product->fresh()->recipeItems);
    }
}
