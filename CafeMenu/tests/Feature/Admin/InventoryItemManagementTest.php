<?php

namespace Tests\Feature\Admin;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemManagementTest extends TestCase
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

    public function test_owner_can_view_and_manage_inventory_items(): void
    {
        $owner = $this->userWithRole('owner');
        $unit = $this->gramUnit();
        $item = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $unit->id, 'is_active' => true]);

        $this->actingAs($owner)->get(route('admin.inventory.items.index'))->assertOk()->assertSee('Coffee Beans');
        $this->actingAs($owner)->get(route('admin.inventory.items.create'))->assertOk();
        $this->actingAs($owner)->get(route('admin.inventory.items.edit', $item))->assertOk();
    }

    public function test_kitchen_and_waiter_are_forbidden_from_inventory_items(): void
    {
        $unit = $this->gramUnit();
        $item = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $unit->id, 'is_active' => true]);

        foreach (['kitchen', 'waiter'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.inventory.items.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.inventory.items.create'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.inventory.items.store'), ['name' => 'x'])->assertForbidden();
            $this->actingAs($user)->get(route('admin.inventory.items.edit', $item))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.inventory.items.destroy', $item))->assertForbidden();
        }
    }

    public function test_manager_can_create_update_and_delete_an_unused_item(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = $this->gramUnit();
        $category = InventoryCategory::create(['name' => 'Beverages']);

        $response = $this->actingAs($manager)->post(route('admin.inventory.items.store'), [
            'name' => 'Espresso Beans',
            'sku' => 'ESP-1',
            'inventory_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'min_stock_level' => '100',
            'max_stock_level' => '5000',
            'is_active' => '1',
        ]);
        $response->assertRedirect(route('admin.inventory.items.index'));

        $item = InventoryItem::where('sku', 'ESP-1')->firstOrFail();
        $this->assertSame('Espresso Beans', $item->name);

        $this->actingAs($manager)->put(route('admin.inventory.items.update', $item), [
            'name' => 'Espresso Beans (Dark Roast)',
            'base_unit_id' => $unit->id,
            'is_active' => '1',
        ])->assertRedirect(route('admin.inventory.items.index'));

        $this->assertSame('Espresso Beans (Dark Roast)', $item->fresh()->name);

        $this->actingAs($manager)->delete(route('admin.inventory.items.destroy', $item));
        $this->assertModelMissing($item);
    }

    public function test_deleting_an_item_with_stock_history_deactivates_it_instead(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = $this->gramUnit();
        $item = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $unit->id, 'is_active' => true]);
        app(StockMovementService::class)->record($item, 'opening_balance', '100');

        $this->actingAs($manager)->delete(route('admin.inventory.items.destroy', $item))
            ->assertRedirect(route('admin.inventory.items.index'));

        $this->assertModelExists($item);
        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_deleting_an_item_referenced_only_by_a_draft_purchase_deactivates_it_instead(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = $this->gramUnit();
        $item = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $unit->id, 'is_active' => true]);
        $purchase = \App\Models\Purchase::create(['supplier_id' => \App\Models\Supplier::create(['name' => 'Acme'])->id, 'purchase_date' => now()]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'inventory_item_id' => $item->id,
            'unit_id' => $unit->id,
            'quantity' => '10',
            'unit_cost' => 100,
            'line_total' => 1000,
        ]);

        $this->actingAs($manager)->delete(route('admin.inventory.items.destroy', $item));

        $this->assertModelExists($item);
        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_unit_crud_happy_path(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('admin.inventory.units.store'), [
            'name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'mass', 'base_factor' => '1000',
        ])->assertRedirect(route('admin.inventory.units.index'));

        $unit = InventoryUnit::where('name', 'Kilogram')->firstOrFail();

        $this->actingAs($manager)->put(route('admin.inventory.units.update', $unit), [
            'name' => 'Kilo', 'abbreviation' => 'kg', 'type' => 'mass', 'base_factor' => '1000',
        ])->assertRedirect(route('admin.inventory.units.index'));
        $this->assertSame('Kilo', $unit->fresh()->name);

        $this->actingAs($manager)->delete(route('admin.inventory.units.destroy', $unit));
        $this->assertModelMissing($unit);
    }

    public function test_deleting_a_unit_referenced_by_an_item_is_blocked(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = $this->gramUnit();
        InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $unit->id, 'is_active' => true]);

        $response = $this->actingAs($manager)->delete(route('admin.inventory.units.destroy', $unit));

        $response->assertRedirect(route('admin.inventory.units.index'));
        $this->assertModelExists($unit);
    }

    public function test_category_crud_happy_path_and_delete_guard(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('admin.inventory.categories.store'), ['name' => 'Dairy'])
            ->assertRedirect(route('admin.inventory.categories.index'));

        $category = InventoryCategory::where('name', 'Dairy')->firstOrFail();

        $this->actingAs($manager)->put(route('admin.inventory.categories.update', $category), ['name' => 'Dairy & Milk'])
            ->assertRedirect(route('admin.inventory.categories.index'));
        $this->assertSame('Dairy & Milk', $category->fresh()->name);

        $unit = $this->gramUnit();
        InventoryItem::create(['name' => 'Milk', 'inventory_category_id' => $category->id, 'base_unit_id' => $unit->id, 'is_active' => true]);

        $blocked = $this->actingAs($manager)->delete(route('admin.inventory.categories.destroy', $category));
        $blocked->assertRedirect(route('admin.inventory.categories.index'));
        $this->assertModelExists($category);

        $empty = InventoryCategory::create(['name' => 'Empty']);
        $this->actingAs($manager)->delete(route('admin.inventory.categories.destroy', $empty));
        $this->assertModelMissing($empty);
    }
}
