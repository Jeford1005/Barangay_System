<?php

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_command_reports_a_clean_test_database(): void
    {
        $this->artisan('system:health')
            ->expectsOutputToContain('Database: OK')
            ->expectsOutputToContain('Cache: OK')
            ->assertExitCode(0);
    }

    public function test_health_command_supports_json_output(): void
    {
        $this->artisan('system:health --json')
            ->assertExitCode(0);
    }
}
