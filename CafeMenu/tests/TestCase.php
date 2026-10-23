<?php

namespace Tests;

use App\Services\Installer\EnvWriter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Installer controllers resolve EnvWriter from the container, which
        // by default writes straight to the real .env file. No test should
        // ever touch this project's actual .env (real dev/demo secrets),
        // regardless of which installer step a test exercises — redirect
        // every container-resolved instance to a throwaway path.
        $this->app->bind(EnvWriter::class, fn () => new EnvWriter(sys_get_temp_dir().'/cafe-test-env-'.getmypid()));
    }

    protected function tearDown(): void
    {
        @unlink(sys_get_temp_dir().'/cafe-test-env-'.getmypid());

        parent::tearDown();
    }
}
