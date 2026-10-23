<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    private const SUPPORTED = ['en', 'fa', 'ar'];

    public function update(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, self::SUPPORTED, true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }
}
