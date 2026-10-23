<?php

namespace App\Models\Concerns;

use App\Models\BusinessSetting;

/**
 * Manually-entered per-locale overrides for a fixed set of text fields
 * (declared per-model via $translatable), stored in one `translations` JSON
 * column shaped {locale: {field: value}}. The business's own home locale
 * always reads straight from the plain column (e.g. `name`) — translations
 * only ever cover the OTHER supported locales, mirroring how ProductPrice
 * overrides the home price rather than replacing it.
 *
 * There is no machine translation anywhere in this app: a field with no
 * translation entered for the current locale simply falls back to the home
 * column's value, so the menu never shows a blank name just because staff
 * haven't gotten to translating it yet.
 */
trait HasTranslations
{
    public function translate(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();

        if ($locale === BusinessSetting::current()->locale) {
            return $this->getAttribute($field);
        }

        $value = data_get($this->translations, "{$locale}.{$field}");

        return filled($value) ? $value : $this->getAttribute($field);
    }

    /**
     * @param  array<string, array<string, string|null>>  $submitted  [locale => [field => value]], as posted by an admin form
     */
    public function syncTranslations(array $submitted): void
    {
        $homeLocale = BusinessSetting::current()->locale;
        $current = $this->translations ?? [];

        foreach ($submitted as $locale => $fields) {
            if ($locale === $homeLocale) {
                continue;
            }

            foreach ($fields as $field => $value) {
                if (! in_array($field, $this->translatable ?? [], true)) {
                    continue;
                }

                if (filled($value)) {
                    $current[$locale][$field] = $value;
                } else {
                    unset($current[$locale][$field]);
                }
            }

            if (empty($current[$locale])) {
                unset($current[$locale]);
            }
        }

        $this->translations = empty($current) ? null : $current;
        $this->save();
    }
}
