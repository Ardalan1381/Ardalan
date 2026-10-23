<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards against future edits to lang/en/*.php silently drifting out of
 * sync with lang/fa/*.php and lang/ar/*.php — every key added to English
 * must get a translation in both, or this fails loudly instead of a
 * customer seeing raw "admin.some.key" strings in the UI.
 *
 * Compares the raw arrays directly (via require) rather than Lang::has(),
 * which misinterprets literal dots inside a key — e.g. validation.php's
 * 'social_links.instagram' — as further nesting.
 */
class LocalizationTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function localeProvider(): array
    {
        return [['fa'], ['ar']];
    }

    #[DataProvider('localeProvider')]
    public function test_every_english_translation_key_exists_in_locale(string $locale): void
    {
        $missing = [];
        $extra = [];

        foreach (glob(lang_path('en/*.php')) as $file) {
            $group = basename($file, '.php');
            $localeFile = lang_path("{$locale}/{$group}.php");

            if (! file_exists($localeFile)) {
                $missing[] = "{$group} (whole file)";

                continue;
            }

            $englishKeys = $this->flattenKeys(require $file);
            $localeKeys = $this->flattenKeys(require $localeFile);

            foreach (array_diff($englishKeys, $localeKeys) as $key) {
                $missing[] = "{$group}.{$key}";
            }

            foreach (array_diff($localeKeys, $englishKeys) as $key) {
                $extra[] = "{$group}.{$key}";
            }
        }

        $this->assertEmpty($missing, "Missing \"{$locale}\" translations for: ".implode(', ', $missing));
        $this->assertEmpty($extra, "\"{$locale}\" has keys not present in English (likely a typo) for: ".implode(', ', $extra));
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<int, string>
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value) && ! array_is_list($value)) {
                $keys = [...$keys, ...$this->flattenKeys($value, $path)];
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    }
}
