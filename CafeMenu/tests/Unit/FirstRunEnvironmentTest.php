<?php

namespace Tests\Unit;

use App\Support\FirstRunEnvironment;
use PHPUnit\Framework\TestCase;

class FirstRunEnvironmentTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/cafemenu-firstrun-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/storage/app/private', 0777, true);
        file_put_contents($this->dir.'/.env.example', "APP_NAME=CafeMenu\nAPP_KEY=\nREVERB_APP_ID=\nREVERB_APP_KEY=\nREVERB_APP_SECRET=\nVITE_REVERB_APP_KEY=\"\${REVERB_APP_KEY}\"\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function test_creates_env_and_generates_missing_secrets(): void
    {
        FirstRunEnvironment::prepare($this->dir);

        $env = file_get_contents($this->dir.'/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=$/m', $env);
        $this->assertMatchesRegularExpression('/^REVERB_APP_ID=\d{6}$/m', $env);
        $this->assertMatchesRegularExpression('/^REVERB_APP_KEY=[0-9a-f]{20}$/m', $env);
        $this->assertStringContainsString('VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"', $env);
    }

    public function test_never_overwrites_existing_values(): void
    {
        file_put_contents($this->dir.'/.env', "APP_KEY=base64:existing\nREVERB_APP_ID=1\nREVERB_APP_KEY=k\nREVERB_APP_SECRET=s\n");

        FirstRunEnvironment::prepare($this->dir);

        $this->assertSame("APP_KEY=base64:existing\nREVERB_APP_ID=1\nREVERB_APP_KEY=k\nREVERB_APP_SECRET=s\n", file_get_contents($this->dir.'/.env'));
    }

    public function test_does_nothing_once_installed(): void
    {
        file_put_contents($this->dir.'/storage/app/private/installed.lock', 'x');
        file_put_contents($this->dir.'/.env', "APP_KEY=\n");

        FirstRunEnvironment::prepare($this->dir);

        $this->assertSame("APP_KEY=\n", file_get_contents($this->dir.'/.env'));
    }
}
