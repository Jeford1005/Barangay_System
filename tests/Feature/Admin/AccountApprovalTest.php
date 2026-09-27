<?php

namespace Tests\Feature\Admin;

use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function pendingApplicant(): User
    {
        return User::factory()->create([
            'user_type' => 'resident',
            'status' => 'pending',
            'name' => 'Maria Dela Cruz',
            'email' => 'maria@example.com',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['user_type' => 'admin', 'status' => 'approved']);
    }

    public function test_admin_can_see_pending_applications(): void
    {
        $applicant = $this->pendingApplicant();
        $purok = Purok::factory()->create();
        Resident::factory()->create([
            'user_id' => $applicant->id,
            'purok_id' => $purok->id,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/approvals')
            ->assertOk()
            ->assertSee('Maria Dela Cruz')
            ->assertSee('maria@example.com')
            ->assertViewHas('pending', fn ($pending) => $pending->getCollection()->every(
                fn (User $applicant) => $applicant->residentProfile?->relationLoaded('purok') === true,
            ));
    }

    public function test_residents_cannot_open_the_approvals_page(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        Resident::factory()->create(['user_id' => $resident->id]);

        $this->actingAs($resident)
            ->get('/admin/approvals')
            ->assertRedirect(route('dashboard'));
    }

    public function test_pending_resident_cannot_sign_in(): void
    {
        $applicant = $this->pendingApplicant();

        $this->post('/login', [
            'email' => $applicant->email,
            'password' => 'password',
            'user_type' => 'resident',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_rejected_resident_gets_a_distinct_message(): void
    {
        $applicant = User::factory()->create([
            'user_type' => 'resident',
            'status' => 'rejected',
            'rejection_reason' => 'Address could not be verified.',
        ]);

        $this->post('/login', [
            'email' => $applicant->email,
            'password' => 'password',
            'user_type' => 'resident',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $errors = session('errors')->all();
        $this->assertStringContainsString('was not approved', reset($errors));
    }

    public function test_approved_resident_can_sign_in(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        Resident::factory()->create(['user_id' => $resident->id]);

        $this->post('/login', [
            'email' => $resident->email,
            'password' => 'password',
            'user_type' => 'resident',
        ]);

        $this->assertAuthenticatedAs($resident);
    }

    public function test_admin_can_approve_an_application(): void
    {
        Notification::fake();
        $applicant = $this->pendingApplicant();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/admin/approvals/{$applicant->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('success');

        $applicant->refresh();
        $this->assertSame('approved', $applicant->status);
        $this->assertSame($admin->id, $applicant->reviewed_by);
        $this->assertNotNull($applicant->approved_at);

        Notification::assertSentTo($applicant, AccountApprovedNotification::class);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.approved',
            'user_id' => $applicant->id,
        ]);
    }

    public function test_admin_can_reject_with_a_reason(): void
    {
        Notification::fake();
        $applicant = $this->pendingApplicant();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/admin/approvals/{$applicant->id}/reject", [
                'reason' => 'Address could not be verified.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $applicant->refresh();
        $this->assertSame('rejected', $applicant->status);
        $this->assertSame('Address could not be verified.', $applicant->rejection_reason);

        Notification::assertSentTo($applicant, AccountRejectedNotification::class, function ($notification) {
            return $notification->reason === 'Address could not be verified.';
        });

        $this->assertDatabaseHas('audit_logs', ['event' => 'account.rejected']);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $applicant = $this->pendingApplicant();

        $this->actingAs($this->admin())
            ->post("/admin/approvals/{$applicant->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $applicant->fresh()->status);
    }

    public function test_already_decided_accounts_cannot_be_re_decided(): void
    {
        $approved = User::factory()->create([
            'user_type' => 'resident',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/approvals/{$approved->id}/approve")
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->post("/admin/approvals/{$approved->id}/reject", ['reason' => 'why not'])
            ->assertNotFound();
    }

    public function test_the_full_lifecycle_from_registration_to_sign_in(): void
    {
        Notification::fake();

        // 1. Register.
        $this->post('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birth_date' => '1990-01-01',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'email' => 'juan@example.com',
            'address' => '12 Mabini St.',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ]);

        $applicant = User::where('email', 'juan@example.com')->first();

        // 2. Cannot sign in while pending.
        $this->post('/login', [
            'email' => 'juan@example.com',
            'password' => 'secure-password',
            'user_type' => 'resident',
        ]);
        $this->assertGuest();

        // 3. Admin approves.
        $this->actingAs($this->admin())
            ->post("/admin/approvals/{$applicant->id}/approve");

        // Registration stores a claim; staff explicitly creates the profile.
        $this->actingAs($this->admin())
            ->post("/admin/users/{$applicant->id}/resident-profile-from-application")
            ->assertRedirect();

        $this->post('/logout'); // end the admin session so the login route is reachable again

        // 4. Sign-in works, and lands on the resident portal.
        $this->post('/login', [
            'email' => 'juan@example.com',
            'password' => 'secure-password',
            'user_type' => 'resident',
        ]);

        $this->assertAuthenticatedAs($applicant);
    }
}
