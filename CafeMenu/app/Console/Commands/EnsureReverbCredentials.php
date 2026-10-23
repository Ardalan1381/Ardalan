<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * .env.example ships REVERB_APP_ID/KEY/SECRET as blank placeholders. Laravel's
 * own `reverb:install` only ever APPENDS a variable that's entirely missing
 * from .env — it skips one that's already present but empty — so copying
 * .env.example to .env (the documented first step of every install path)
 * silently leaves real-time broadcasting (waiter calls, order/kitchen
 * updates) permanently broken: the Reverb server and the browser's Echo
 * client end up "agreeing" on an empty key, which produces no visible error
 * but also never delivers anything live.
 *
 * This generates real values in place exactly once, the same way
 * `key:generate` does for APP_KEY — safe to run on every `composer run
 * setup`/deploy since it never touches an already-populated value.
 */
class EnsureReverbCredentials extends Command
{
    protected $signature = 'app:ensure-reverb-credentials {--path= : The .env file to update (defaults to the project root .env)}';

    protected $description = 'Generate Reverb app credentials in .env if they are still blank';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('.env');

        if (! file_exists($path)) {
            return self::SUCCESS;
        }

        $contents = file_get_contents($path);
        $generated = [];

        foreach (['REVERB_APP_ID' => fn () => (string) random_int(100_000, 999_999),
            'REVERB_APP_KEY' => fn () => Str::lower(Str::random(20)),
            'REVERB_APP_SECRET' => fn () => Str::lower(Str::random(20))] as $key => $makeValue) {
            if (! $this->isBlankOrMissing($contents, $key)) {
                continue;
            }

            $value = $makeValue();
            $generated[] = $key;

            $contents = preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)
                ? preg_replace('/^'.preg_quote($key, '/').'=.*$/m', "{$key}={$value}", $contents)
                : rtrim($contents)."\n{$key}={$value}\n";
        }

        if ($generated === []) {
            $this->components->info('Reverb credentials already set — nothing to do.');

            return self::SUCCESS;
        }

        file_put_contents($path, $contents);
        $this->components->info('Generated Reverb app credentials: '.implode(', ', $generated).'.');

        return self::SUCCESS;
    }

    private function isBlankOrMissing(string $contents, string $key): bool
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return true;
        }

        return trim($matches[1], " \t\"'") === '';
    }
}
