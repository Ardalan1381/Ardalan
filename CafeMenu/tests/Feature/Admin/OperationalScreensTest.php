<?php

namespace Tests\Feature\Admin;

use App\Actions\CreateStaffOrderAction;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressions found by rendering the real screens (see
 * docs/engineering/UI_VERIFICATION.md).
 */
class OperationalScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
    }

    private function dineInOrder(Table $table): Order
    {
        return app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => Product::factory()->create()->id, 'quantity' => 1]],
        ], $this->owner);
    }

    public function test_kitchen_display_shows_whole_minutes_and_urgency(): void
    {
        BusinessSetting::current()->update(['kitchen_delay_threshold_minutes' => 15]);
        $fresh = $this->dineInOrder(Table::factory()->create());
        $late = $this->dineInOrder(Table::factory()->create());
        $fresh->forceFill(['status_changed_at' => now()->subSeconds(115)])->save();
        $late->forceFill(['status_changed_at' => now()->subMinutes(20)->subSeconds(30)])->save();

        $html = $this->actingAs($this->owner)->get(route('admin.kitchen.index'))->assertOk()->getContent();

        $this->assertStringContainsString('1 min ago', $html);
        $this->assertStringContainsString('20 min ago', $html);
        $this->assertDoesNotMatchRegularExpression('/\d+\.\d+ min ago/', $html);
        $this->assertStringContainsString('data-urgency="delayed"', $html);
        $this->assertStringContainsString('data-urgency="normal"', $html);
    }

    public function test_pos_shows_a_single_state_per_table(): void
    {
        $occupied = Table::factory()->create(['name' => 'Occupied table']);
        $this->dineInOrder($occupied);
        Table::factory()->create(['name' => 'Free table']);
        Table::factory()->create(['name' => 'Broken table', 'status' => 'out_of_service']);

        $html = $this->actingAs($this->owner)->get(route('admin.pos.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-table-state="occupied"'));
        $this->assertSame(1, substr_count($html, 'data-table-state="available"'));
        $this->assertSame(1, substr_count($html, 'data-table-state="out_of_service"'));
    }

    public function test_product_labels_are_translated_on_the_menu_and_pos(): void
    {
        Product::factory()->create(['label' => 'popular']);

        $this->withSession(['locale' => 'fa'])->get(route('menu.index'))->assertOk()->assertSee('پرطرفدار')->assertDontSee('>Popular<', false);
        $this->actingAs($this->owner)->withSession(['locale' => 'fa'])->get(route('admin.pos.counter'))->assertOk()->assertSee('پرطرفدار');
    }

    public function test_sidebar_groups_only_contain_links_the_user_may_see(): void
    {
        $kitchen = User::factory()->create(['role_id' => Role::where('name', 'kitchen')->first()->id]);

        $html = $this->actingAs($kitchen)->get(route('admin.kitchen.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="nav-group', $html);
        $this->assertStringNotContainsString(route('admin.users.index'), $html);
        $this->assertStringNotContainsString(route('admin.reports.index'), $html);
    }
}
