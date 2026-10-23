<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetLocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_config_locale_when_nothing_else_is_set(): void
    {
        $this->get('/');

        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    public function test_business_setting_locale_overrides_config_default(): void
    {
        BusinessSetting::current()->update(['locale' => 'fa']);

        $this->get('/');

        $this->assertSame('fa', app()->getLocale());
    }

    public function test_session_locale_overrides_business_setting(): void
    {
        BusinessSetting::current()->update(['locale' => 'fa']);

        $this->withSession(['locale' => 'en'])->get('/');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_locale_update_route_stores_valid_locale_in_session(): void
    {
        $this->from('/')->post(route('locale.update', 'fa'))->assertRedirect('/');

        $this->assertSame('fa', session('locale'));
    }

    public function test_arabic_locale_is_supported_and_sets_rtl_direction(): void
    {
        $this->from('/')->post(route('locale.update', 'ar'))->assertRedirect('/');
        $this->assertSame('ar', session('locale'));

        $response = $this->withSession(['locale' => 'ar'])->get('/');

        $this->assertSame('ar', app()->getLocale());
        $response->assertSee('dir="rtl"', false);
    }

    public function test_locale_update_route_ignores_unsupported_locale(): void
    {
        $this->from('/')->post(route('locale.update', 'xx'))->assertRedirect('/');

        $this->assertNull(session('locale'));
    }

    public function test_invalid_session_locale_falls_back_to_business_setting(): void
    {
        BusinessSetting::current()->update(['locale' => 'fa']);

        $this->withSession(['locale' => 'xx'])->get('/');

        $this->assertSame('fa', app()->getLocale());
    }
}
