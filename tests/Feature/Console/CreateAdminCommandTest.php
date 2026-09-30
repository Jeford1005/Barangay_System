<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the app:create-admin bootstrap command — the supported way to
 * create the first administrator on hosts without shell access. No suite
 * exercised it before: every other test seeds admins through factories.
 */
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_admin_from_options(): void
    {
        $this->artisan('app:create-admin', [
            '--name' => 'Barangay Admin',
            '--email' => 'admin@example.com',
            '--password' => 'super-secret-1',
        ])->assertExitCode(0);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('admin', $admin->user_type);
        $this->assertSame('approved', $admin->status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotNull($admin->approved_at);
        $this->assertTrue(Hash::check('super-secret-1', $admin->password));
    }

    public function test_it_refuses_a_second_admin_without_force(): void
    {
        User::factory()->admin()->create();

        $this->artisan('app:create-admin', [
            '--name' => 'Second Admin',
            '--email' => 'second@example.com',
            '--password' => 'super-secret-2',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_it_refuses_to_overwrite_an_existing_email_without_force(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('app:create-admin', [
            '--name' => 'Someone Else',
            '--email' => 'taken@example.com',
            '--password' => 'super-secret-3',
        ])->assertExitCode(1);

        // The original password survives the refused overwrite.
        $this->assertTrue(Hash::check('password', User::where('email', 'taken@example.com')->firstOrFail()->password));
    }

    public function test_force_replaces_the_existing_account_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'user_type' => 'staff']);

        $this->artisan('app:create-admin', [
            '--name' => 'Promoted Admin',
            '--email' => 'taken@example.com',
            '--password' => 'brand-new-admin-1',
            '--force' => true,
        ])->assertExitCode(0);

        $updated = User::where('email', 'taken@example.com')->firstOrFail();

        $this->assertSame('admin', $updated->user_type);
        $this->assertTrue(Hash::check('brand-new-admin-1', $updated->password));
    }

    public function test_it_rejects_a_short_password_and_creates_nothing(): void
    {
        $this->artisan('app:create-admin', [
            '--name' => 'Barangay Admin',
            '--email' => 'admin@example.com',
            '--password' => 'short',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
    }
}
