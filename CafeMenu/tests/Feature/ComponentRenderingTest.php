<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ComponentRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Components read $errors (normally shared by the web middleware
        // group on every real request) — share an empty bag so Blade::render()
        // works the same way outside of an HTTP request lifecycle.
        view()->share('errors', new ViewErrorBag);

        // Blade::render()'s string-mode compiler doesn't reliably handle
        // named component slots (<x-slot:name>) — real page rendering is
        // unaffected, but the dropdown/modal tests below render an actual
        // fixture view through the normal file-based compiler to test them.
        View::addLocation(__DIR__.'/fixtures/views');
    }

    public function test_button_renders_as_button_and_as_link(): void
    {
        $this->assertStringContainsString('<button', Blade::render('<x-button>Save</x-button>'));
        $this->assertStringContainsString('<a href="/foo"', Blade::render('<x-button href="/foo">Go</x-button>'));
    }

    public function test_input_renders_label_and_error(): void
    {
        $html = Blade::render('<x-input name="email" label="Email" />');
        $this->assertStringContainsString('Email', $html);
        $this->assertStringContainsString('name="email"', $html);
    }

    public function test_textarea_renders(): void
    {
        $html = Blade::render('<x-textarea name="notes" label="Notes">hello</x-textarea>');
        $this->assertStringContainsString('name="notes"', $html);
        $this->assertStringContainsString('hello', $html);
    }

    public function test_select_renders_slot_options(): void
    {
        $html = Blade::render('<x-select name="role"><option value="1">Owner</option></x-select>');
        $this->assertStringContainsString('<option value="1">Owner</option>', $html);
    }

    public function test_checkbox_renders(): void
    {
        $html = Blade::render('<x-checkbox name="active" label="Active" />');
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function test_checkbox_renders_hint_and_passes_through_data_attributes(): void
    {
        $html = Blade::render('<x-checkbox name="active" label="Active" hint="Careful with this one" data-confirm-uncheck="Are you sure?" />');
        $this->assertStringContainsString('Careful with this one', $html);
        $this->assertStringContainsString('data-confirm-uncheck="Are you sure?"', $html);
    }

    /**
     * Regression test: @checked(...) used directly as an attribute on a
     * <x-checkbox> component tag (rather than a native <input>) silently
     * fails to compile — the whole tag is left as literal, non-functional
     * text, and the browser swallows everything after it as an unknown
     * element's children. Every "Active"/"Available" toggle across the
     * admin panel was broken this way until :checked="..." (a real
     * component prop) replaced it. This asserts real, compiled <input>
     * markup — not just that some text appears on the page — so it
     * actually fails if the component tag stops compiling again.
     */
    public function test_checkbox_checked_prop_compiles_to_a_real_input_element(): void
    {
        $checkedHtml = Blade::render('<x-checkbox name="is_active" label="Active" :checked="true" />');
        $this->assertMatchesRegularExpression('/<input[^>]+type="checkbox"[^>]+checked[^>]*>/', $checkedHtml);
        $this->assertStringNotContainsString('<x-checkbox', $checkedHtml);

        $uncheckedHtml = Blade::render('<x-checkbox name="is_active" label="Active" :checked="false" />');
        $this->assertDoesNotMatchRegularExpression('/<input[^>]+type="checkbox"[^>]+checked[^>]*>/', $uncheckedHtml);
        $this->assertStringNotContainsString('<x-checkbox', $uncheckedHtml);
    }

    public function test_radio_renders(): void
    {
        $html = Blade::render('<x-radio name="type" value="cash" label="Cash" />');
        $this->assertStringContainsString('type="radio"', $html);
    }

    public function test_switch_renders(): void
    {
        $html = Blade::render('<x-switch name="enabled" label="Enabled" />');
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('peer', $html);
    }

    public function test_card_renders_title_and_slot(): void
    {
        $html = Blade::render('<x-card title="Summary">Body</x-card>');
        $this->assertStringContainsString('Summary', $html);
        $this->assertStringContainsString('Body', $html);
    }

    public function test_badge_maps_known_statuses_to_variants(): void
    {
        $this->assertStringContainsString('bg-success-subtle', Blade::render('<x-badge status="paid">Paid</x-badge>'));
        $this->assertStringContainsString('bg-danger-subtle', Blade::render('<x-badge status="failed">Failed</x-badge>'));
        $this->assertStringContainsString('bg-gray-100', Blade::render('<x-badge status="something-unknown">?</x-badge>'));
    }

    public function test_alert_renders_variant_styles(): void
    {
        $this->assertStringContainsString('bg-danger-subtle', Blade::render('<x-alert type="danger">Oops</x-alert>'));
    }

    public function test_empty_state_renders(): void
    {
        $html = Blade::render('<x-empty-state title="Nothing here" description="Try again later" />');
        $this->assertStringContainsString('Nothing here', $html);
        $this->assertStringContainsString('Try again later', $html);
    }

    public function test_skeleton_renders_variants(): void
    {
        $this->assertStringContainsString('rounded-full', Blade::render('<x-skeleton variant="circle" />'));
    }

    public function test_stat_card_renders_label_value_and_icon(): void
    {
        $html = view('stat-card-fixture')->render();
        $this->assertStringContainsString('Sales today', $html);
        $this->assertStringContainsString('$120.00', $html);
        $this->assertStringContainsString('ICON', $html);
        $this->assertStringContainsString('bg-success-subtle', $html);
    }

    public function test_nav_link_renders_active_state_href_and_icon(): void
    {
        $html = view('nav-link-fixture')->render();
        $this->assertStringContainsString('href="/somewhere"', $html);
        $this->assertStringContainsString('Somewhere', $html);
        $this->assertStringContainsString('ICON', $html);
        $this->assertStringContainsString('bg-primary/10', $html);
    }

    public function test_dropdown_renders_trigger_and_slot(): void
    {
        $html = view('dropdown-fixture')->render();
        $this->assertStringContainsString('Open', $html);
        $this->assertStringContainsString('Panel content', $html);
        $this->assertStringContainsString('<details', $html);
    }

    public function test_modal_renders_with_title_and_footer(): void
    {
        $html = view('modal-fixture')->render();
        $this->assertStringContainsString('id="my-modal"', $html);
        $this->assertStringContainsString('Confirm', $html);
        $this->assertStringContainsString('Footer', $html);
        $this->assertStringContainsString('<dialog', $html);
    }

    public function test_table_renders_headers_and_rows(): void
    {
        $html = Blade::render('<x-table :headers="[\'Name\', \'Email\']"><tr><td>Row</td></tr></x-table>');
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Row', $html);
    }

    public function test_toast_container_renders(): void
    {
        $html = Blade::render('<x-toast-container />');
        $this->assertStringContainsString('id="toast-container"', $html);
    }

    public function test_theme_toggle_renders_button_with_both_icons(): void
    {
        $html = Blade::render('<x-theme-toggle />');
        $this->assertStringContainsString('data-theme-toggle', $html);
        $this->assertStringContainsString('dark:hidden', $html);
        $this->assertStringContainsString('dark:block', $html);
    }
}
