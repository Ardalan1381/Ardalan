<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * No RefreshDatabase here — this command never touches the database, only
 * a .env-shaped file, and always via --path so the real project .env is
 * never at risk of being rewritten by the test suite.
 */
class EnsureReverbCredentialsTest extends TestCase
{
    private function fixturePath(): string
    {
        return storage_path('app/testing/reverb-credentials.env');
    }

    protected function tearDown(): void
    {
        File::delete($this->fixturePath());

        parent::tearDown();
    }

    public function test_generates_credentials_when_blank(): void
    {
        $path = $this->fixturePath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, <<<'ENV'
            APP_NAME=Cafe
            REVERB_APP_ID=
            REVERB_APP_KEY=
            REVERB_APP_SECRET=
            REVERB_HOST="localhost"
            ENV);

        $this->artisan('app:ensure-reverb-credentials', ['--path' => $path])->assertExitCode(0);

        $contents = File::get($path);
        $this->assertMatchesRegularExpression('/^REVERB_APP_ID=\d{6}$/m', $contents);
        $this->assertMatchesRegularExpression('/^REVERB_APP_KEY=[a-z0-9]{20}$/m', $contents);
        $this->assertMatchesRegularExpression('/^REVERB_APP_SECRET=[a-z0-9]{20}$/m', $contents);
        // Untouched lines must survive exactly as they were.
        $this->assertStringContainsString('APP_NAME=Cafe', $contents);
        $this->assertStringContainsString('REVERB_HOST="localhost"', $contents);
    }

    public function test_never_overwrites_already_populated_credentials(): void
    {
        $path = $this->fixturePath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, <<<'ENV'
            REVERB_APP_ID=123456
            REVERB_APP_KEY=existingkey00000000
            REVERB_APP_SECRET=existingsecret00000
            ENV);

        $this->artisan('app:ensure-reverb-credentials', ['--path' => $path])->assertExitCode(0);

        $contents = File::get($path);
        $this->assertStringContainsString('REVERB_APP_ID=123456', $contents);
        $this->assertStringContainsString('REVERB_APP_KEY=existingkey00000000', $contents);
        $this->assertStringContainsString('REVERB_APP_SECRET=existingsecret00000', $contents);
    }

    public function test_generates_only_the_blank_ones_and_leaves_the_rest(): void
    {
        $path = $this->fixturePath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, <<<'ENV'
            REVERB_APP_ID=999999
            REVERB_APP_KEY=
            REVERB_APP_SECRET=alreadyset0000000000
            ENV);

        $this->artisan('app:ensure-reverb-credentials', ['--path' => $path])->assertExitCode(0);

        $contents = File::get($path);
        $this->assertStringContainsString('REVERB_APP_ID=999999', $contents);
        $this->assertStringContainsString('REVERB_APP_SECRET=alreadyset0000000000', $contents);
        $this->assertDoesNotMatchRegularExpression('/^REVERB_APP_KEY=\s*$/m', $contents);
    }

    public function test_missing_env_file_is_a_no_op(): void
    {
        $this->artisan('app:ensure-reverb-credentials', ['--path' => storage_path('app/testing/does-not-exist.env')])
            ->assertExitCode(0);
    }
}
