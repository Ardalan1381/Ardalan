<?php

namespace App\Services\Installer;

class InstallerRequirements
{
    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl', 'tokenizer',
        'xml', 'ctype', 'json', 'gd', 'fileinfo', 'curl', 'bcmath',
    ];

    private const WRITABLE_PATHS = [
        'storage/app',
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ];

    /**
     * @return array<int, array{label: string, passed: bool}>
     */
    public function checks(): array
    {
        $checks = [
            [
                'label' => 'PHP version >= 8.2 (running '.PHP_VERSION.')',
                'passed' => version_compare(PHP_VERSION, '8.2.0', '>='),
            ],
            [
                'label' => 'Application key is set (run "php artisan key:generate" on the server if missing)',
                'passed' => filled(config('app.key')),
            ],
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $checks[] = [
                'label' => "PHP extension: {$extension}",
                'passed' => extension_loaded($extension),
            ];
        }

        return $checks;
    }

    public function passes(): bool
    {
        return collect($this->checks())->every(fn (array $check) => $check['passed']);
    }

    /**
     * @return array<int, array{label: string, passed: bool}>
     */
    public function writableChecks(): array
    {
        return array_map(fn (string $path) => [
            'label' => $path,
            'passed' => is_writable(base_path($path)),
        ], self::WRITABLE_PATHS);
    }

    public function pathsWritable(): bool
    {
        return collect($this->writableChecks())->every(fn (array $check) => $check['passed']);
    }

    public function envFileWritable(): bool
    {
        return is_writable(base_path('.env'));
    }
}
