<?php

namespace App\Support;

/**
 * Makes a freshly uploaded copy bootable without shell access.
 *
 * Shared-hosting buyers often cannot run `php artisan key:generate`; without
 * an APP_KEY every request is a bare HTTP 500 ("No application encryption
 * key has been specified") and the web installer can never be reached. So,
 * on a web request to a copy that has never been installed, this creates
 * .env from .env.example and fills in APP_KEY and the Reverb credentials —
 * exactly what `key:generate` and `app:ensure-reverb-credentials` would do.
 *
 * Runs before the framework boots (called from bootstrap/app.php), so it
 * uses plain PHP only. It never runs once the install lock exists, and
 * never overwrites a value that is already set.
 */
final class FirstRunEnvironment
{
    public static function prepare(string $basePath): void
    {
        if (is_file($basePath.'/storage/app/private/installed.lock')) {
            return;
        }

        $env = $basePath.'/.env';
        $example = $basePath.'/.env.example';

        if (! is_file($env)) {
            if (! is_file($example) || ! is_writable($basePath) || ! @copy($example, $env)) {
                return;
            }
            @chmod($env, 0640);
        }

        if (! is_writable($env)) {
            return;
        }

        $contents = (string) file_get_contents($env);
        $original = $contents;

        $generators = [
            'APP_KEY' => fn () => 'base64:'.base64_encode(random_bytes(32)),
            'REVERB_APP_ID' => fn () => (string) random_int(100_000, 999_999),
            'REVERB_APP_KEY' => fn () => bin2hex(random_bytes(10)),
            'REVERB_APP_SECRET' => fn () => bin2hex(random_bytes(10)),
        ];

        foreach ($generators as $key => $generate) {
            $pattern = '/^'.preg_quote($key, '/').'=(.*)$/m';

            if (preg_match($pattern, $contents, $match)) {
                if (trim($match[1], " \t\"'") !== '') {
                    continue;
                }
                $contents = preg_replace($pattern, $key.'='.$generate(), $contents, 1);
            } else {
                $contents = rtrim($contents)."\n".$key.'='.$generate()."\n";
            }
        }

        if ($contents !== $original) {
            file_put_contents($env, $contents, LOCK_EX);
        }
    }
}
