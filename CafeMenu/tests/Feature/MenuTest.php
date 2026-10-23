<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_shows_active_categories_with_available_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->get(route('menu.index'))->assertOk()->assertSee($category->name)->assertSee($product->name);
    }

    public function test_menu_hides_inactive_categories(): void
    {
        $category = Category::factory()->create(['is_active' => false]);
        Product::factory()->create(['category_id' => $category->id]);

        $this->get(route('menu.index'))->assertOk()->assertDontSee($category->name);
    }

    public function test_menu_hides_unavailable_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_available' => false,
        ]);

        $this->get(route('menu.index'))->assertOk()->assertDontSee($product->name);
    }

    public function test_menu_hides_inactive_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this->get(route('menu.index'))->assertOk()->assertDontSee($product->name);
    }

    public function test_menu_hides_categories_with_no_visible_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);

        $this->get(route('menu.index'))->assertOk()->assertDontSee($category->name);
    }

    public function test_menu_shows_subcategory_products(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $product = Product::factory()->create(['category_id' => $child->id]);

        $this->get(route('menu.index'))
            ->assertOk()
            ->assertSee($parent->name)
            ->assertSee($child->name)
            ->assertSee($product->name);
    }

    public function test_scanning_a_table_qr_sets_session_and_redirects_to_menu(): void
    {
        $table = Table::factory()->create();

        $response = $this->get('/t/'.$table->qr_token);

        $response->assertRedirect(route('menu.index'));
        $this->assertEquals($table->id, session('dining_table_id'));
    }

    public function test_scanning_an_inactive_table_qr_returns_not_found(): void
    {
        $table = Table::factory()->create(['is_active' => false]);

        $this->get('/t/'.$table->qr_token)->assertNotFound();
    }

    public function test_scanning_an_invalid_qr_token_returns_not_found(): void
    {
        $this->get('/t/not-a-real-token')->assertNotFound();
    }

    public function test_menu_shows_table_banner_after_scanning_qr(): void
    {
        $table = Table::factory()->create();

        $this->get('/t/'.$table->qr_token);
        $this->get(route('menu.index'))->assertOk()->assertSee($table->name);
    }

    public function test_menu_shows_out_of_service_notice_for_scanned_table(): void
    {
        $table = Table::factory()->create(['status' => 'out_of_service']);

        $this->get('/t/'.$table->qr_token);
        $this->get(route('menu.index'))->assertOk()->assertSee('temporarily unavailable');
    }

    public function test_product_detail_page_shows_option_groups(): void
    {
        $product = Product::factory()->create();
        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Size',
            'selection_type' => 'single',
            'min_select' => 1,
            'is_active' => true,
        ]);
        $group->options()->create(['name' => 'Large', 'price_delta' => 500, 'is_active' => true]);

        $this->get(route('menu.show', $product))
            ->assertOk()
            ->assertSee('Size')
            ->assertSee('Large');
    }

    public function test_inactive_product_detail_page_returns_not_found(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->get(route('menu.show', $product))->assertNotFound();
    }

    public function test_unavailable_product_is_still_viewable_but_marked(): void
    {
        $product = Product::factory()->create(['is_available' => false]);

        $this->get(route('menu.show', $product))->assertOk()->assertSee('Currently unavailable');
    }
}
