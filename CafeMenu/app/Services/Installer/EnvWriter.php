<?php

namespace App\Services\Installer;

/**
 * Rewrites specific keys in the .env file in place, preserving every other
 * line. Values containing whitespace or special characters are quoted.
 */
class EnvWriter
{
    public function __construct(
        private readonly string $path = '',
    ) {}

    /**
     * @param  array<string, string>  $values
     */
    public function set(array $values): void
    {
        $path = $this->path !== '' ? $this->path : base_path('.env');
        $contents = file_exists($path) ? file_get_contents($path) : '';
        $lines = $contents !== '' ? explode("\n", $contents) : [];

        foreach ($values as $key => $value) {
            $formatted = $this->formatValue($value);
            $found = false;

            foreach ($lines as $index => $line) {
                if (preg_match('/^'.preg_quote($key, '/').'=/', $line)) {
                    $lines[$index] = "{$key}={$formatted}";
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $lines[] = "{$key}={$formatted}";
            }
        }

        file_put_contents($path, implode("\n", $lines));
    }

    private function formatValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\'\\\\]/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
