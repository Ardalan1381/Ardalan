<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended($this->defaultLandingPage($request->user()));
    }

    /**
     * The dashboard surfaces revenue figures, which not every staff role can
     * see (e.g. kitchen/barista only have order permissions). Send those
     * roles straight to the orders queue instead of a dashboard they'd be
     * denied access to.
     */
    private function defaultLandingPage(User $user): string
    {
        if ($user->hasPermission('dashboard.view')) {
            return route('admin.dashboard');
        }

        if ($user->hasPermission('orders.view')) {
            return route('admin.orders.index');
        }

        return route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        app(AuditLogger::class)->log('auth.logout');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
