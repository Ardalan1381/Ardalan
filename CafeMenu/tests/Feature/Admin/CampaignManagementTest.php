<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\Discount;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignManagementTest extends TestCase
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

    public function test_owner_can_view_campaigns_list(): void
    {
        $owner = $this->userWithRole('owner');
        $campaign = Campaign::create(['name' => 'Summer Sale']);

        $this->actingAs($owner)->get(route('admin.campaigns.index'))->assertOk()->assertSee($campaign->name);
        $this->actingAs($owner)->get(route('admin.campaigns.create'))->assertOk();
        $this->actingAs($owner)->get(route('admin.campaigns.edit', $campaign))->assertOk();
    }

    public function test_kitchen_cannot_view_campaigns_list(): void
    {
        $kitchen = $this->userWithRole('kitchen');

        $this->actingAs($kitchen)->get(route('admin.campaigns.index'))->assertForbidden();
    }

    public function test_cashier_cannot_manage_campaigns(): void
    {
        // Cashiers get discounts.view/apply but not discounts.manage, and
        // campaigns are gated by discounts.manage alone (no separate view tier).
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->get(route('admin.campaigns.index'))->assertForbidden();
    }

    public function test_owner_can_create_a_campaign(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.campaigns.store'), [
            'name' => 'Winter Promo',
            'description' => 'Seasonal promotion',
            'starts_at' => '2026-12-01',
            'ends_at' => '2026-12-31',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.campaigns.index'));
        $this->assertDatabaseHas('campaigns', ['name' => 'Winter Promo', 'is_active' => true]);
    }

    public function test_campaign_ends_at_must_be_after_or_equal_to_starts_at(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post(route('admin.campaigns.store'), [
            'name' => 'Invalid dates',
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('ends_at');
    }

    public function test_owner_can_update_a_campaign(): void
    {
        $owner = $this->userWithRole('owner');
        $campaign = Campaign::create(['name' => 'Original name']);

        $response = $this->actingAs($owner)->put(route('admin.campaigns.update', $campaign), [
            'name' => 'Renamed',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.campaigns.index'));
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'name' => 'Renamed', 'is_active' => false]);
    }

    public function test_cannot_delete_campaign_with_discounts_attached(): void
    {
        $owner = $this->userWithRole('owner');
        $campaign = Campaign::create(['name' => 'Summer Sale']);
        Discount::create([
            'name' => 'Tied discount', 'type' => 'percentage', 'value' => 500, 'applies_to' => 'order', 'campaign_id' => $campaign->id,
        ]);

        $response = $this->actingAs($owner)->delete(route('admin.campaigns.destroy', $campaign));

        $response->assertRedirect(route('admin.campaigns.index'));
        $this->assertModelExists($campaign);
    }

    public function test_can_delete_an_empty_campaign(): void
    {
        $owner = $this->userWithRole('owner');
        $campaign = Campaign::create(['name' => 'Empty campaign']);

        $this->actingAs($owner)->delete(route('admin.campaigns.destroy', $campaign));

        $this->assertModelMissing($campaign);
    }
}
