<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOptionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function owner(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', 'owner')->first()->id,
        ]);
    }

    public function test_owner_can_add_an_option_group_and_an_option(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();

        $this->actingAs($owner)->post(route('admin.products.option-groups.store', $product), [
            'name' => 'Size',
            'selection_type' => 'single',
            'min_select' => 1,
            'max_select' => 1,
            'is_required' => '1',
        ])->assertRedirect();

        $group = $product->optionGroups()->firstOrFail();
        $this->assertSame('Size', $group->name);

        $this->actingAs($owner)->post(route('admin.products.option-groups.options.store', [$product, $group]), [
            'name' => 'Large',
            'price_delta' => 500,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_options', [
            'product_option_group_id' => $group->id,
            'name' => 'Large',
            'price_delta' => 500,
        ]);

        $this->actingAs($owner)->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Size')
            ->assertSee('Large');
    }

    public function test_single_selection_group_cannot_allow_more_than_one_max_select(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();

        $response = $this->actingAs($owner)->post(route('admin.products.option-groups.store', $product), [
            'name' => 'Size',
            'selection_type' => 'single',
            'min_select' => 0,
            'max_select' => 3,
        ]);

        $response->assertSessionHasErrors('max_select');
    }

    public function test_required_group_must_have_minimum_selection_of_at_least_one(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();

        $response = $this->actingAs($owner)->post(route('admin.products.option-groups.store', $product), [
            'name' => 'Add-ons',
            'selection_type' => 'multiple',
            'min_select' => 0,
            'is_required' => '1',
        ]);

        $response->assertSessionHasErrors('min_select');
    }

    public function test_max_select_cannot_be_less_than_min_select(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();

        $response = $this->actingAs($owner)->post(route('admin.products.option-groups.store', $product), [
            'name' => 'Add-ons',
            'selection_type' => 'multiple',
            'min_select' => 3,
            'max_select' => 1,
        ]);

        $response->assertSessionHasErrors('max_select');
    }

    public function test_cannot_update_an_option_group_that_belongs_to_a_different_product_idor(): void
    {
        $owner = $this->owner();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $group = ProductOptionGroup::create([
            'product_id' => $productB->id,
            'name' => 'Size',
            'selection_type' => 'single',
            'min_select' => 0,
        ]);

        $response = $this->actingAs($owner)->put(route('admin.products.option-groups.update', [$productA, $group]), [
            'name' => 'Hacked',
            'selection_type' => 'single',
            'min_select' => 0,
        ]);

        $response->assertNotFound();
        $this->assertSame('Size', $group->fresh()->name);
    }
}
