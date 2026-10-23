<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every page to the installer while the application has never been
 * set up — no lock file AND no usable schema (no database configured yet,
 * or not migrated). A database that is already migrated is never redirected
 * here, so a live site that merely lost its lock file keeps working (and
 * EnsureNotInstalled keeps the installer shut for it).
 */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install', 'install/*') || app(InstallLock::class)->isInstalled() || $this->schemaExists()) {
            return $next($request);
        }

        return redirect()->route('install.requirements');
    }

    private function schemaExists(): bool
    {
        try {
            return Schema::hasTable('users');
        } catch (\Throwable) {
            return false;
        }
    }
}
