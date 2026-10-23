<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manually-entered per-locale name/description overrides for the catalog —
 * mirrors ProductPrice's design (home locale stays on the plain column,
 * other locales are opt-in overrides with no automatic translation and a
 * safe fallback to the home text when nothing has been entered yet).
 */
class CatalogTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        BusinessSetting::current()->update(['locale' => 'fa']);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->first()->id]);
    }

    public function test_translate_returns_the_home_column_for_the_home_locale(): void
    {
        $product = Product::factory()->create(['name' => 'لاته']);
        $product->syncTranslations(['en' => ['name' => 'Latte']]);

        $this->assertSame('لاته', $product->translate('name', 'fa'));
    }

    public function test_translate_returns_the_override_for_another_locale(): void
    {
        $product = Product::factory()->create(['name' => 'لاته']);
        $product->syncTranslations(['en' => ['name' => 'Latte']]);

        $this->assertSame('Latte', $product->translate('name', 'en'));
    }

    public function test_translate_falls_back_to_home_text_when_no_override_exists(): void
    {
        $product = Product::factory()->create(['name' => 'لاته']);

        $this->assertSame('لاته', $product->translate('name', 'en'));
    }

    public function test_sync_translations_preserves_other_locales_when_only_one_is_submitted(): void
    {
        $product = Product::factory()->create(['name' => 'لاته']);
        $product->syncTranslations(['en' => ['name' => 'Latte'], 'ar' => ['name' => 'لاتيه']]);

        $product->syncTranslations(['en' => ['name' => 'Latte (updated)']]);

        $this->assertSame('Latte (updated)', $product->translate('name', 'en'));
        $this->assertSame('لاتيه', $product->translate('name', 'ar'));
    }

    public function test_blanking_a_translation_removes_it_and_falls_back_to_home(): void
    {
        $product = Product::factory()->create(['name' => 'لاته']);
        $product->syncTranslations(['en' => ['name' => 'Latte']]);

        $product->syncTranslations(['en' => ['name' => '']]);

        $this->assertSame('لاته', $product->translate('name', 'en'));
    }

    public function test_owner_can_save_a_product_name_and_description_translation(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'لاته']);

        $this->actingAs($owner)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'base_price' => $product->base_price,
            'translations' => ['en' => ['name' => 'Latte', 'description' => 'Espresso with steamed milk']],
        ])->assertRedirect(route('admin.products.edit', $product));

        $product->refresh();
        $this->assertSame('Latte', $product->translate('name', 'en'));
        $this->assertSame('Espresso with steamed milk', $product->translate('description', 'en'));
        $this->assertSame('لاته', $product->name);
    }

    public function test_owner_can_save_a_category_translation(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create(['name' => 'نوشیدنی‌های گرم']);

        $this->actingAs($owner)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'translations' => ['en' => ['name' => 'Hot Drinks']],
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertSame('Hot Drinks', $category->fresh()->translate('name', 'en'));
    }

    public function test_owner_can_save_an_option_group_translation(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create();
        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'سایز',
            'selection_type' => 'single',
            'min_select' => 1,
        ]);

        $this->actingAs($owner)->put(route('admin.products.option-groups.update', [$product, $group]), [
            'name' => $group->name,
            'selection_type' => 'single',
            'min_select' => 1,
            'translations' => ['en' => ['name' => 'Size']],
        ])->assertRedirect(route('admin.products.edit', $product));

        $this->assertSame('Size', $group->fresh()->translate('name', 'en'));
    }

    public function test_owner_can_save_an_option_translation(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create();
        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'سایز',
            'selection_type' => 'single',
            'min_select' => 0,
        ]);
        $option = ProductOption::create([
            'product_option_group_id' => $group->id,
            'name' => 'بزرگ',
            'price_delta' => 0,
        ]);

        $this->actingAs($owner)->put(route('admin.products.option-groups.options.update', [$product, $group, $option]), [
            'name' => $option->name,
            'price_delta' => $option->price_delta,
            'translations' => ['en' => ['name' => 'Large']],
        ])->assertRedirect(route('admin.products.edit', $product));

        $this->assertSame('Large', $option->fresh()->translate('name', 'en'));
    }

    public function test_menu_shows_the_translated_product_and_category_name_in_that_locale(): void
    {
        $category = Category::factory()->create(['name' => 'نوشیدنی‌های گرم', 'is_active' => true]);
        $category->syncTranslations(['en' => ['name' => 'Hot Drinks']]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'لاته',
            'is_active' => true,
            'is_available' => true,
        ]);
        $product->syncTranslations(['en' => ['name' => 'Latte']]);

        $response = $this->withSession(['locale' => 'en'])->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('Hot Drinks');
        $response->assertSee('Latte');
        $response->assertDontSee('لاته');
    }

    public function test_menu_falls_back_to_home_name_when_no_translation_is_set(): void
    {
        $category = Category::factory()->create(['name' => 'نوشیدنی‌های گرم', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'لاته',
            'is_active' => true,
            'is_available' => true,
        ]);

        $response = $this->withSession(['locale' => 'en'])->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('لاته');
    }
}
