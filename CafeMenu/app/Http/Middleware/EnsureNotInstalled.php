<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks re-running the installer once the app is installed — running it
 * again could reconfigure or wipe a live production database.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(InstallLock::class)->isInstalled()) {
            return redirect('/')->with('error', 'This application is already installed.');
        }

        return $next($request);
    }
}
