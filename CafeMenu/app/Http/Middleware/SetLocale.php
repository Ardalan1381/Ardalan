<?php

namespace App\Http\Middleware;

use App\Models\BusinessSetting;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported locales, in priority order: an explicit ?lang= on the
     * current URL (used by per-language QR codes — see the table/menu QR
     * controllers) wins over a per-visitor choice already in session (set
     * via the language switcher or a previous ?lang= visit), which wins
     * over the business's configured default, which wins over the app
     * config fallback.
     */
    private const SUPPORTED = ['en', 'fa', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        // App::setLocale() only sets the translator locale — Carbon's
        // diffForHumans() phrasing needs its own locale set explicitly, or
        // it stays in English regardless of the app locale.
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $queryLocale = $request->query('lang');

        // A ?lang= on the URL is a deliberate choice (someone scanned a
        // language-specific QR code) — it must win immediately, on this
        // very request, not just the next one. It's also persisted to
        // session so the customer doesn't need it on every subsequent page
        // as they browse the menu.
        if (is_string($queryLocale) && in_array($queryLocale, self::SUPPORTED, true)) {
            $request->session()->put('locale', $queryLocale);

            return $queryLocale;
        }

        $sessionLocale = $request->session()->get('locale');

        if (is_string($sessionLocale) && in_array($sessionLocale, self::SUPPORTED, true)) {
            return $sessionLocale;
        }

        return $this->businessLocale() ?? config('app.locale');
    }

    /**
     * Guarded broadly because this runs on every request, including before
     * the installer has configured a database connection or created the
     * business_settings table on a fresh install. Reads only — must never
     * create a row here (BusinessSetting::current() does via firstOrCreate),
     * or the installer's "is business setup complete?" check would see a
     * row that only exists because a page was merely *viewed*.
     */
    private function businessLocale(): ?string
    {
        try {
            if (! Schema::hasTable('business_settings')) {
                return null;
            }

            $locale = BusinessSetting::query()->value('locale');

            return in_array($locale, self::SUPPORTED, true) ? $locale : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
