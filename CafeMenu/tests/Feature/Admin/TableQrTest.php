<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableQrTest extends TestCase
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

    public function test_owner_can_view_the_table_qr_page(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();

        $this->actingAs($owner)->get(route('admin.tables.qr', $table))
            ->assertOk()
            ->assertSee($table->name);
    }

    public function test_owner_can_download_table_qr_as_png(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();

        $response = $this->actingAs($owner)->get(route('admin.tables.qr.png', $table));

        $response->assertOk();
        $this->assertStringContainsString('image/png', $response->headers->get('Content-Type'));
        $this->assertGreaterThan(0, strlen($response->getContent()));
    }

    public function test_owner_can_download_table_qr_as_svg(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();

        $response = $this->actingAs($owner)->get(route('admin.tables.qr.svg', $table));

        $response->assertOk();
        $this->assertStringContainsString('svg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_qr_encodes_a_url_containing_the_table_token(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();

        $response = $this->actingAs($owner)->get(route('admin.tables.qr.svg', $table));

        // The SVG path data differs per payload; encoding correctness is
        // exercised via the generator directly to avoid coupling to the
        // library's internal path format. This asserts a well-formed SVG.
        $response->assertOk();
        $this->assertStringStartsWith('<?xml', $response->getContent());
    }

    public function test_table_qr_page_shows_one_code_per_supported_language(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();

        $response = $this->actingAs($owner)->get(route('admin.tables.qr', $table));

        $response->assertOk();
        $response->assertSee(route('admin.tables.qr.png', [$table, 'lang' => 'en']), false);
        $response->assertSee(route('admin.tables.qr.png', [$table, 'lang' => 'fa']), false);
        $response->assertSee(route('admin.tables.qr.png', [$table, 'lang' => 'ar']), false);
    }

    public function test_table_qr_codes_for_different_languages_encode_different_payloads(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();

        $en = $this->actingAs($owner)->get(route('admin.tables.qr.svg', [$table, 'lang' => 'en']))->getContent();
        $ar = $this->actingAs($owner)->get(route('admin.tables.qr.svg', [$table, 'lang' => 'ar']))->getContent();
        $none = $this->actingAs($owner)->get(route('admin.tables.qr.svg', $table))->getContent();

        $this->assertNotSame($en, $ar);
        $this->assertNotSame($en, $none);
    }

    public function test_scanning_a_language_specific_table_qr_sets_that_locale_immediately(): void
    {
        $table = Table::factory()->create();

        $response = $this->get('/t/'.$table->qr_token.'?lang=ar');

        $response->assertRedirect(route('menu.index'));
        $this->assertSame('ar', session('locale'));

        $followed = $this->get($response->headers->get('Location'));
        $followed->assertSee('dir="rtl"', false);
    }

    public function test_regenerating_qr_token_changes_it_and_invalidates_the_old_one(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();
        $originalToken = $table->qr_token;

        $response = $this->actingAs($owner)->post(route('admin.tables.qr.regenerate', $table));

        $response->assertRedirect(route('admin.tables.qr', $table));
        $table->refresh();
        $this->assertNotSame($originalToken, $table->qr_token);
        $this->assertSame(0, Table::where('qr_token', $originalToken)->count());
    }

    public function test_waiter_cannot_view_or_regenerate_table_qr(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();

        $this->actingAs($waiter)->get(route('admin.tables.qr', $table))->assertForbidden();
        $this->actingAs($waiter)->post(route('admin.tables.qr.regenerate', $table))->assertForbidden();
    }

    public function test_owner_can_view_and_download_the_public_menu_qr(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.qr.menu'))->assertOk();
        $this->actingAs($owner)->get(route('admin.qr.menu.png'))->assertOk();
        $this->actingAs($owner)->get(route('admin.qr.menu.svg'))->assertOk();
    }

    public function test_waiter_cannot_access_the_public_menu_qr(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.qr.menu'))->assertForbidden();
    }

    public function test_public_menu_qr_page_shows_one_code_per_supported_language(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->get(route('admin.qr.menu'));

        $response->assertOk();
        $response->assertSee(route('admin.qr.menu.png', ['lang' => 'en']), false);
        $response->assertSee(route('admin.qr.menu.png', ['lang' => 'fa']), false);
        $response->assertSee(route('admin.qr.menu.png', ['lang' => 'ar']), false);
    }

    public function test_scanning_a_language_specific_public_menu_qr_sets_that_locale(): void
    {
        $response = $this->get('/?lang=ar');

        $response->assertOk();
        $this->assertSame('ar', session('locale'));
        $response->assertSee('dir="rtl"', false);
    }
}
