<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks re-running the installer once the app is installed — running it
 * again could reconfigure or wipe a live production database.
 *
 * The lock file alone is not enough: storage/ is easily lost on a redeploy
 * (a fresh release directory, a restored backup without it), and an open
 * installer on a live site lets anyone repoint .env at a database they
 * control. So a database that already has user accounts also counts as
 * installed — unless this very browser session created the owner account
 * a moment ago and is still finishing the remaining installer steps.
 */
class EnsureNotInstalled
{
    public const SESSION_OWNER_KEY = 'installer.owner_id';

    public function handle(Request $request, Closure $next): Response
    {
        $lock = app(InstallLock::class);

        if ($lock->isInstalled()) {
            return redirect('/')->with('error', 'This application is already installed.');
        }

        if ($this->databaseHasUsers() && ! $request->session()->has(self::SESSION_OWNER_KEY)) {
            // Restore the missing lock so the decision is permanent.
            $lock->lock();

            return redirect('/')->with('error', 'This application is already installed.');
        }

        return $next($request);
    }

    private function databaseHasUsers(): bool
    {
        try {
            return Schema::hasTable('users') && DB::table('users')->exists();
        } catch (\Throwable) {
            // No usable database yet — a genuinely fresh install.
            return false;
        }
    }
}
