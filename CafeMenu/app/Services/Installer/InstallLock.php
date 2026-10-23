<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\Storage;

/**
 * A plain file marker, not a DB flag: the whole point is to know whether the
 * app is installed before we can trust the database is even configured.
 * Uses the "local" disk so tests can swap it out via Storage::fake().
 */
class InstallLock
{
    private const FILENAME = 'installed.lock';

    public function isInstalled(): bool
    {
        return Storage::disk('local')->exists(self::FILENAME);
    }

    public function lock(): void
    {
        Storage::disk('local')->put(self::FILENAME, now()->toIso8601String());
    }
}
