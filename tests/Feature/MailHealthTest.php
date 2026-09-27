<?php

namespace Tests\Feature;

use App\Mail\MailSetupTestMail;
use App\Models\User;
use App\Services\MailHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);
    }

    public function test_report_flags_log_driver_as_not_ready(): void
    {
        config(['mail.default' => 'log']);

        $report = app(MailHealthChecker::class)->run();

        $this->assertFalse($report['ready']);
        $this->assertSame('log', $report['mailer']);
        $this->assertSame('warn', collect($report['checks'])->firstWhere('label', 'Mailer driver')['status']);
    }

    public function test_report_passes_with_valid_gmail_config(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => '587',
            'mail.mailers.smtp.username' => 'barangay@gmail.com',
            'mail.mailers.smtp.password' => 'abcdefghijklmnop',
            'mail.from.address' => 'barangay@gmail.com',
        ]);

        $report = app(MailHealthChecker::class)->run();

        $this->assertTrue($report['ready']);

        $statuses = collect($report['checks'])->pluck('status', 'label');
        $this->assertSame('pass', $statuses['SMTP host']);
        $this->assertSame('pass', $statuses['SMTP port']);
        $this->assertSame('pass', $statuses['SMTP username']);
        $this->assertSame('pass', $statuses['SMTP password (App Password)']);
        $this->assertSame('pass', $statuses['From address']);
    }

    public function test_report_fails_when_gmail_from_address_differs_from_username(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => '587',
            'mail.mailers.smtp.username' => 'barangay@gmail.com',
            'mail.mailers.smtp.password' => 'abcdefghijklmnop',
            'mail.from.address' => 'someone.else@gmail.com',
        ]);

        $report = app(MailHealthChecker::class)->run();

        $this->assertFalse($report['ready']);
        $this->assertSame('fail', collect($report['checks'])->firstWhere('label', 'From address')['status']);
    }

    public function test_report_warns_when_password_does_not_look_like_an_app_password(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => '587',
            'mail.mailers.smtp.username' => 'barangay@gmail.com',
            'mail.mailers.smtp.password' => 'my-gmail-login-password', // not 16 alnum chars
            'mail.from.address' => 'barangay@gmail.com',
        ]);

        $report = app(MailHealthChecker::class)->run();

        $this->assertFalse($report['ready']);
        $this->assertSame('warn', collect($report['checks'])->firstWhere('label', 'SMTP password (App Password)')['status']);
    }

    public function test_report_fails_when_smtp_credentials_are_empty(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => '587',
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => 'barangay@example.com',
        ]);

        $report = app(MailHealthChecker::class)->run();

        $this->assertFalse($report['ready']);
        $statuses = collect($report['checks'])->pluck('status', 'label');
        $this->assertSame('fail', $statuses['SMTP username']);
        $this->assertSame('fail', $statuses['SMTP password (App Password)']);
    }

    public function test_health_page_requires_authentication(): void
    {
        $this->get('/admin/mail-health')->assertRedirect('/login');
    }

    public function test_health_page_renders_checklist_for_admins(): void
    {
        $this->actingAs($this->admin)->get('/admin/mail-health')
            ->assertOk()
            ->assertSee('Mail Health Check')
            ->assertSee('Send a test email')
            ->assertSee('Gmail quick setup');
    }

    public function test_sending_a_test_email_dispatches_a_message(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post('/admin/mail-health/send-test', ['email' => 'inbox@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(MailSetupTestMail::class, function (MailSetupTestMail $mailable) {
            return true;
        });
    }

    public function test_blank_test_recipient_requires_a_configured_destination(): void
    {
        Mail::fake();
        config(['mail.from.address' => null]);

        $this->actingAs($this->admin)
            ->post('/admin/mail-health/send-test')
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_sending_test_email_is_rate_limited(): void
    {
        Mail::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($this->admin)
                ->post('/admin/mail-health/send-test', ['email' => 'inbox@example.com'])
                ->assertRedirect();
        }

        $this->actingAs($this->admin)
            ->post('/admin/mail-health/send-test', ['email' => 'inbox@example.com'])
            ->assertStatus(429);
    }

    public function test_command_reports_status_and_exit_code(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('mail:check')
            ->expectsOutputToContain('Mail is not ready')
            ->assertExitCode(1);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => '587',
            'mail.mailers.smtp.username' => 'barangay@gmail.com',
            'mail.mailers.smtp.password' => 'abcdefghijklmnop',
            'mail.from.address' => 'barangay@gmail.com',
        ]);

        $this->artisan('mail:check')
            ->expectsOutputToContain('Mail is ready')
            ->assertExitCode(0);
    }

    public function test_command_sends_a_test_email_when_asked(): void
    {
        Mail::fake();

        config(['mail.default' => 'log']); // sending works, but config stays "not ready" (log driver)

        $this->artisan('mail:check', ['--send' => true])
            ->expectsOutputToContain('Test email')
            ->assertExitCode(1); // still not production-ready — only the log driver is configured

        Mail::assertSent(MailSetupTestMail::class);
    }
}
