<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
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

    public function test_owner_can_view_products_index_and_edit_pages(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create();

        $this->actingAs($owner)->get(route('admin.products.index'))->assertOk()->assertSee($product->name);
        $this->actingAs($owner)->get(route('admin.products.create'))->assertOk();
        $this->actingAs($owner)->get(route('admin.products.edit', $product))->assertOk()->assertSee($product->name);
    }

    public function test_edit_page_warns_before_unchecking_active_or_available(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create(['is_active' => true, 'is_available' => true]);

        $this->actingAs($owner)->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('data-confirm-uncheck="'.__('admin.products.confirm_hide_from_menu').'"', false);
    }

    /**
     * Regression test for a real incident: the "Active"/"Available"
     * checkboxes on this exact page used to render as literal, non-functional
     * text (see ComponentRenderingTest::test_checkbox_checked_prop_compiles_to_a_real_input_element
     * for why), so the browser never submitted them — every save silently
     * turned an active product inactive. Asserts real, checked <input>
     * elements are actually present in the rendered page, not just that
     * some related text appears.
     */
    public function test_edit_page_renders_real_checked_checkboxes_for_an_active_product(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create(['is_active' => true, 'is_available' => true]);

        $html = $this->actingAs($owner)->get(route('admin.products.edit', $product))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]+name="is_active"[^>]+checked[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+name="is_available"[^>]+checked[^>]*>/', $html);
        $this->assertStringNotContainsString('<x-checkbox', $html);
    }

    public function test_updating_a_product_without_touching_active_available_keeps_it_visible_on_the_menu(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        // Mirrors exactly what the real <form> submits when both checkboxes
        // are left checked: is_active/is_available are present as "1".
        $this->actingAs($owner)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'base_price' => $product->base_price,
            'is_active' => '1',
            'is_available' => '1',
        ])->assertRedirect(route('admin.products.edit', $product));

        $product->refresh();
        $this->assertTrue($product->is_active);
        $this->assertTrue($product->is_available);
    }

    public function test_owner_can_create_a_product_with_integer_price(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();

        $response = $this->actingAs($owner)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Espresso',
            'base_price' => 4500,
            'is_available' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'name' => 'Espresso',
            'base_price' => 4500,
            'category_id' => $category->id,
        ]);
    }

    public function test_owner_can_set_a_price_override_for_an_active_display_currency(): void
    {
        BusinessSetting::current()->update([
            'display_currencies' => ['en' => ['is_active' => true, 'code' => 'USD', 'symbol' => '$', 'minor_units' => 2]],
        ]);
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'base_price' => 65000]);

        $this->actingAs($owner)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'base_price' => $product->base_price,
            'is_active' => '1',
            'is_available' => '1',
            'prices' => ['en' => ['price' => '250']],
        ])->assertRedirect(route('admin.products.edit', $product));

        $override = $product->prices()->where('locale', 'en')->firstOrFail();
        $this->assertSame(250, $override->price);
        $this->assertNull($override->discount_price);
    }

    public function test_blanking_a_price_override_removes_it(): void
    {
        BusinessSetting::current()->update([
            'display_currencies' => ['en' => ['is_active' => true, 'code' => 'USD', 'symbol' => '$', 'minor_units' => 2]],
        ]);
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $product->prices()->create(['locale' => 'en', 'price' => 250]);

        $this->actingAs($owner)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'base_price' => $product->base_price,
            'is_active' => '1',
            'is_available' => '1',
            'prices' => ['en' => ['price' => '']],
        ])->assertRedirect(route('admin.products.edit', $product));

        $this->assertSame(0, $product->prices()->where('locale', 'en')->count());
    }

    public function test_currency_override_discount_must_be_less_than_its_own_price(): void
    {
        BusinessSetting::current()->update([
            'display_currencies' => ['en' => ['is_active' => true, 'code' => 'USD', 'symbol' => '$', 'minor_units' => 2]],
        ]);
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($owner)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'base_price' => $product->base_price,
            'prices' => ['en' => ['price' => '250', 'discount_price' => '300']],
        ]);

        $response->assertSessionHasErrors('prices.en.discount_price');
    }

    public function test_product_requires_an_existing_category(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.products.store'), [
            'category_id' => 999999,
            'name' => 'Espresso',
            'base_price' => 4500,
        ]);

        $response->assertSessionHasErrors('category_id');
    }

    public function test_discount_price_must_be_less_than_base_price(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();

        $response = $this->actingAs($owner)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Latte',
            'base_price' => 5000,
            'discount_price' => 5000,
        ]);

        $response->assertSessionHasErrors('discount_price');
    }

    public function test_product_label_must_be_a_known_value(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();

        $response = $this->actingAs($owner)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Latte',
            'base_price' => 5000,
            'label' => 'bestseller-of-all-time',
        ]);

        $response->assertSessionHasErrors('label');
    }

    public function test_effective_price_uses_discount_when_present(): void
    {
        $product = Product::factory()->create(['base_price' => 5000, 'discount_price' => 4000]);

        $this->assertSame(4000, $product->effectivePrice());
    }

    public function test_effective_price_falls_back_to_base_price(): void
    {
        $product = Product::factory()->create(['base_price' => 5000, 'discount_price' => null]);

        $this->assertSame(5000, $product->effectivePrice());
    }

    public function test_waiter_cannot_create_products(): void
    {
        $waiter = $this->userWithRole('waiter');
        $category = Category::factory()->create();

        $response = $this->actingAs($waiter)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Espresso',
            'base_price' => 4500,
        ]);

        $response->assertForbidden();
    }

    public function test_waiter_cannot_directly_access_product_edit_page_idor(): void
    {
        $waiter = $this->userWithRole('waiter');
        $product = Product::factory()->create();

        $this->actingAs($waiter)
            ->get(route('admin.products.edit', $product))
            ->assertForbidden();
    }

    public function test_cannot_delete_a_category_that_still_has_this_product(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create();

        $this->actingAs($owner)->delete(route('admin.categories.destroy', $product->category));

        $this->assertModelExists($product->category);
    }
}
