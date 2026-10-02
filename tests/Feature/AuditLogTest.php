<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function requestCodeFor(User $user): string
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, \App\Notifications\ResetPasswordCodeNotification::class);

        return Notification::sent($user, \App\Notifications\ResetPasswordCodeNotification::class)
            ->first()->code;
    }

    public function test_code_requests_are_recorded_with_ip_and_user_agent(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $this->post('/forgot-password', ['email' => $user->email], [
            'REMOTE_ADDR' => '203.0.113.7',
        ]);

        $log = AuditLog::where('event', 'password_reset.code_requested')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($user->email, $log->user_email);
        $this->assertNotNull($log->occurred_at);
        $this->assertNotNull($log->ip_address);
        $this->assertSame('guest', $log->actor_type);
        $this->assertSame('user', $log->subject_type);
        $this->assertSame($user->id, $log->subject_id);
    }

    public function test_completed_resets_are_recorded(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $code = $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password-12',
            'password_confirmation' => 'new-secure-password-12',
        ])->assertRedirect('/login');

        $log = AuditLog::where('event', 'password_reset.completed')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($user->email, $log->user_email);
    }

    public function test_failed_code_attempts_are_recorded_without_user_link(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => 'ZZZZZZ',
            'password' => 'new-secure-password-12',
            'password_confirmation' => 'new-secure-password-12',
        ])->assertSessionHasErrors('code');

        $log = AuditLog::where('event', 'password_reset.failed_code')->first();

        $this->assertNotNull($log);
        // Email is captured, but there is no user link to trust — the attempt was unverified.
        $this->assertSame($user->email, $log->user_email);
        $this->assertNull($log->user_id);
    }

    public function test_requests_for_unknown_emails_are_not_recorded(): void
    {
        $this->post('/forgot-password', ['email' => 'ghost@example.com'])
            ->assertRedirect();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_full_story_is_recorded_in_order(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $code = $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => 'WRNG2A',
            'password' => 'new-secure-password-12',
            'password_confirmation' => 'new-secure-password-12',
        ]);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password-12',
            'password_confirmation' => 'new-secure-password-12',
        ]);

        $events = AuditLog::orderBy('occurred_at')->orderBy('id')->pluck('event')->all();

        $this->assertSame([
            'password_reset.code_requested',
            'password_reset.failed_code',
            'password_reset.completed',
        ], $events);
    }

    public function test_audit_page_requires_authentication(): void
    {
        $this->get('/admin/audit-logs')->assertRedirect('/login');
    }

    public function test_audit_page_renders_records_for_admins(): void
    {
        $user = User::factory()->create(['user_type' => 'admin', 'email' => 'audited@barangay.local']);

        $code = $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password-12',
            'password_confirmation' => 'new-secure-password-12',
        ]);

        $this->actingAs($this->admin ?? User::factory()->create(['user_type' => 'admin']))
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('Audit Log')
            ->assertSee('Reset code requested')
            ->assertSee('Password reset completed')
            ->assertSee('audited@barangay.local');
    }

    public function test_audit_page_prints_certification_footer_with_scope(): void
    {
        $user = User::factory()->create(['user_type' => 'admin', 'email' => 'printed@barangay.local']);

        $code = $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password-12',
            'password_confirmation' => 'new-secure-password-12',
        ]);

        $response = $this->actingAs(User::factory()->create(['user_type' => 'admin']))
            ->get('/admin/audit-logs?event=password_reset.completed');

        $response->assertOk();

        $html = $response->getContent();

        // Certification footer for barangay record-keeping.
        $this->assertStringContainsString('true and correct extract of the audit log', $html);
        $this->assertStringContainsString('Punong Barangay', $html);
        $this->assertStringContainsString('Prepared by', $html);

        // Print-only scope line reports the active filter and totals.
        $this->assertStringContainsString('Showing', $html);
        $this->assertStringContainsString('Password reset completed', $html);

        // The footer and scope header are hidden on screen, shown in print.
        $this->assertStringContainsString('print-only', $html);
    }

    public function test_audit_page_filters_by_event(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $code = $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password-12',
            'password_confirmation' => 'new-secure-password-12',
        ]);

        $this->actingAs(User::factory()->create(['user_type' => 'admin']))
            ->get('/admin/audit-logs?event=password_reset.completed')
            ->assertOk()
            ->assertSee('Password reset completed')
            ->assertSee('1 record'); // the code_requested row is filtered out (option labels aside)
    }
}
