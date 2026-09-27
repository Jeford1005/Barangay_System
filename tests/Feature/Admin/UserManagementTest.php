<?php

namespace Tests\Feature\Admin;

use App\Models\Resident;
use App\Models\ResidentApplication;
use App\Models\User;
use App\Notifications\AccountAccessChangedNotification;
use App\Notifications\ResetPasswordCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_access_notification_renders_the_selected_change(): void
    {
        $user = User::factory()->resident()->create(['name' => 'Notification Resident']);
        $mail = (new AccountAccessChangedNotification(
            userName: $user->name,
            change: 'suspended',
            reason: 'Pending investigation',
        ))->toMail($user);

        $this->assertStringContainsString('Notification Resident', $mail->render());
        $this->assertStringContainsString('Pending investigation', $mail->render());
    }

    public function test_guests_and_residents_cannot_access_user_accounts(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');

        $resident = User::factory()->resident()->create();

        $this->actingAs($resident)
            ->get('/admin/users')
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_search_and_filter_user_accounts(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Directory Admin']);
        $matching = User::factory()->resident()->create([
            'name' => 'Matching Resident',
            'email' => 'matching@example.com',
        ]);
        User::factory()->resident()->pending()->create([
            'name' => 'Pending Applicant',
            'email' => 'pending@example.com',
        ]);
        User::factory()->resident()->rejected()->create([
            'name' => 'Rejected Applicant',
            'email' => 'rejected@example.com',
        ]);
        User::factory()->resident()->suspended()->create([
            'name' => 'Suspended Resident',
            'email' => 'suspended@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/users?search=matching&role=resident&status=approved');

        $response->assertOk()
            ->assertSee('Matching Resident')
            ->assertSee($matching->email)
            ->assertDontSee('Pending Applicant')
            ->assertDontSee('Rejected Applicant')
            ->assertDontSee('Suspended Resident')
            ->assertSee(route('admin.users.show', $matching));
    }

    public function test_suspended_filter_lists_only_suspended_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->resident()->suspended()->create(['name' => 'Suspended One']);
        User::factory()->resident()->create(['name' => 'Active One']);

        $this->actingAs($admin)
            ->get('/admin/users?status=suspended')
            ->assertOk()
            ->assertSee('Suspended One')
            ->assertDontSee('Active One');
    }

    public function test_admin_can_view_account_details_without_exposing_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->resident()->create(['name' => 'Detail Resident']);
        $profile = Resident::factory()->create(['user_id' => $resident->id]);
        DB::table('password_reset_tokens')->insert([
            'email' => $resident->email,
            'token' => hash('sha256', 'ABC123'),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $resident));

        $response->assertOk()
            ->assertSee('Detail Resident')
            ->assertSee($resident->email)
            ->assertSee($profile->full_name)
            ->assertDontSee($resident->password, false)
            ->assertDontSee('ABC123');
    }

    public function test_admin_can_create_a_profile_from_a_registration_application(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->resident()->create(['status' => 'approved']);
        $application = ResidentApplication::create([
            'user_id' => $account->id,
            'first_name' => 'Applied',
            'last_name' => 'Resident',
            'birth_date' => '1990-01-01',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'email' => $account->email,
            'address' => 'Application Street',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.resident-profile-from-application', $account))
            ->assertRedirect(route('admin.users.show', $account));

        $this->assertDatabaseHas('residents', [
            'user_id' => $account->id,
            'first_name' => 'Applied',
            'last_name' => 'Resident',
        ]);
        $this->assertSame('Approved', $application->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'resident.profile_created_from_application']);
    }

    public function test_admin_can_explicitly_link_a_resident_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => null]);

        $this->actingAs($admin)
            ->patch(route('admin.users.resident-link', $account), [
                'resident_id' => $resident->id,
            ])
            ->assertRedirect(route('admin.users.show', $account));

        $this->assertSame($account->id, $resident->fresh()->user_id);
        $log = \App\Models\AuditLog::where('event', 'account.resident_linked')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($account->id, $log->subject_id);
        $this->assertSame('user', $log->subject_type);
    }

    public function test_admin_can_explicitly_unlink_and_suspend_a_resident_account(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $account->id]);

        $this->actingAs($admin)
            ->post(route('admin.users.resident-unlink', $account))
            ->assertRedirect(route('admin.users.show', $account));

        $this->assertNull($resident->fresh()->user_id);
        $this->assertTrue($account->fresh()->isSuspended());
        $this->assertDatabaseHas('audit_logs', ['event' => 'account.resident_unlinked']);
    }

    public function test_admin_cannot_silently_replace_an_existing_resident_link(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->resident()->create();
        $first = Resident::factory()->create(['user_id' => $account->id]);
        $second = Resident::factory()->create(['user_id' => null]);

        $this->actingAs($admin)
            ->patch(route('admin.users.resident-link', $account), ['resident_id' => $second->id])
            ->assertSessionHasErrors('resident_id');

        $this->assertSame($account->id, $first->fresh()->user_id);
        $this->assertNull($second->fresh()->user_id);
    }

    public function test_admin_cannot_link_a_resident_profile_owned_by_another_account(): void
    {
        $admin = User::factory()->admin()->create();
        $first = User::factory()->resident()->create();
        $second = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $first->id]);

        $this->actingAs($admin)
            ->patch(route('admin.users.resident-link', $second), [
                'resident_id' => $resident->id,
            ])
            ->assertSessionHasErrors('resident_id');

        $this->assertSame($first->id, $resident->fresh()->user_id);
    }

    public function test_admin_can_update_identity_and_email_change_invalidates_credentials(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->resident()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => 'old@example.com',
            'token' => hash('sha256', 'ABC123'),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $resident), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('admin.users.show', $resident));
        $resident->refresh();
        $this->assertSame('New Name', $resident->name);
        $this->assertSame('new@example.com', $resident->email);
        $this->assertNull($resident->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'old@example.com']);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.updated',
            'user_id' => $resident->id,
        ]);
    }

    public function test_email_update_rejects_duplicate_addresses(): void
    {
        $admin = User::factory()->admin()->create();
        $first = User::factory()->resident()->create(['email' => 'first@example.com']);
        $second = User::factory()->resident()->create(['email' => 'second@example.com']);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $second), [
                'name' => $second->name,
                'email' => $first->email,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('second@example.com', $second->fresh()->email);
    }

    public function test_admin_can_change_an_active_users_role_but_not_own_role(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();
        Resident::factory()->create(['user_id' => $target->id]);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $target), ['user_type' => 'resident'])
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertSame('resident', $target->fresh()->user_type);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.role_changed',
            'user_id' => $target->id,
        ]);
        Notification::assertSentTo($target, AccountAccessChangedNotification::class);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['user_type' => 'resident'])
            ->assertSessionHasErrors('user_type');
    }

    public function test_pending_account_cannot_be_promoted_to_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->resident()->pending()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $pending), ['user_type' => 'admin'])
            ->assertSessionHasErrors('user_type');

        $this->assertSame('resident', $pending->fresh()->user_type);
    }

    public function test_admin_can_suspend_and_reactivate_an_account(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->resident()->create();
        DB::table('password_reset_tokens')->insert([
            'email' => $resident->email,
            'token' => hash('sha256', 'ABC123'),
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.show', $resident))
            ->post(route('admin.users.suspend', $resident), [
                'reason' => 'Account is under review',
            ])
            ->assertRedirect(route('admin.users.show', $resident));

        $resident->refresh();
        $this->assertTrue($resident->isSuspended());
        $this->assertSame('Account is under review', $resident->suspension_reason);
        $this->assertSame($admin->id, $resident->suspended_by);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $resident->email]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.suspended',
            'user_id' => $resident->id,
        ]);
        Notification::assertSentTo($resident, AccountAccessChangedNotification::class);

        $this->actingAs($admin)
            ->post(route('admin.users.reactivate', $resident))
            ->assertRedirect(route('admin.users.show', $resident));

        $resident->refresh();
        $this->assertFalse($resident->isSuspended());
        $this->assertNull($resident->suspension_reason);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.reactivated',
            'user_id' => $resident->id,
        ]);
    }

    public function test_admin_cannot_suspend_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $admin))
            ->post(route('admin.users.suspend', $admin), [
                'reason' => 'Should be blocked',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($admin->fresh()->isSuspended());
    }

    public function test_suspended_user_cannot_login_or_use_existing_session(): void
    {
        $resident = User::factory()->resident()->suspended()->create([
            'email' => 'suspended-login@example.com',
        ]);

        $this->post('/login', [
            'email' => $resident->email,
            'password' => 'password',
            'user_type' => 'resident',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($resident)
            ->get('/my')
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_send_reset_code_without_exposing_it(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->resident()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.reset', $resident));

        $response->assertRedirect(route('admin.users.show', $resident))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $resident->email]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.password_reset_initiated',
            'user_id' => $resident->id,
        ]);
        $code = null;
        Notification::assertSentTo($resident, ResetPasswordCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->assertNotNull($code);
        $this->assertStringNotContainsString($code, $response->getContent());
    }

    public function test_pending_account_cannot_receive_admin_reset_code(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->resident()->pending()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.reset', $pending))
            ->assertSessionHasErrors('user');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_root_routes_residents_to_their_portal(): void
    {
        $resident = User::factory()->resident()->create();

        $this->actingAs($resident)->get('/')->assertRedirect(route('resident.portal'));
    }

    public function test_residents_cannot_open_admin_audit_or_mail_routes(): void
    {
        $resident = User::factory()->resident()->create();

        $this->actingAs($resident)->get('/admin/audit-logs')->assertRedirect(route('dashboard'));
        $this->actingAs($resident)->get('/admin/mail-health')->assertRedirect(route('dashboard'));
    }
}
