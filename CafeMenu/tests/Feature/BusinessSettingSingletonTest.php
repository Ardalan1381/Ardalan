<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression: current() used firstOrCreate(['id' => 1]), but 'id' is not
 * fillable, so the insert silently dropped it and took the next
 * auto-increment value. Whenever the sequence had already moved past 1
 * (a rolled-back installer insert, a deleted row, MySQL's non-transactional
 * AUTO_INCREMENT), every call created a brand-new row and saved settings
 * were never read back.
 */
class BusinessSettingSingletonTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_always_resolves_the_same_single_row_even_after_the_sequence_moved_on(): void
    {
        // Burn the sequence past 1, as a rolled-back insert would on MySQL.
        DB::table('business_settings')->insert(['id' => 7, 'name' => 'burned', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('business_settings')->where('id', 7)->delete();

        $first = BusinessSetting::current();
        $first->update(['name' => 'My Cafe']);

        $this->assertSame(1, $first->id);
        $this->assertSame('My Cafe', BusinessSetting::current()->name);
        $this->assertSame(1, BusinessSetting::query()->count());
    }

    public function test_current_returns_defaults_for_a_freshly_created_row(): void
    {
        $settings = BusinessSetting::current();

        $this->assertTrue($settings->exists);
        $this->assertTrue($settings->accepts_dine_in);
        $this->assertSame(config('app.name'), $settings->name);
    }
}
