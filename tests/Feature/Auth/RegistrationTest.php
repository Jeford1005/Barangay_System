<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => null,
            'birth_date' => '1995-06-15',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'phone_number' => '09171234567',
            'email' => 'maria@example.com',
            'address' => '123 Rizal St.',
            'purok_id' => null,
            'household_id' => null,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ], $overrides);
    }

    public function test_registration_screen_renders_for_guests(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Create your resident account');
    }

    public function test_a_resident_can_register_and_starts_pending(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $response->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user = User::where('email', 'maria@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('resident', $user->user_type);
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->approved_at);

        // Registration creates an application claim, not an authoritative
        // resident record. Staff must explicitly link or create the profile.
        $application = ResidentApplication::where('user_id', $user->id)->first();
        $this->assertNotNull($application);
        $this->assertSame('Maria', $application->first_name);
        $this->assertSame('Dela Cruz', $application->last_name);
        $this->assertDatabaseMissing('residents', ['user_id' => $user->id]);

        // The applicant is NOT logged in — approval comes first.
        $this->assertGuest();
    }

    public function test_registration_accepts_the_birth_date_boundary_shown_by_the_form(): void
    {
        $this->post('/register', $this->validPayload([
            'birth_date' => '1900-01-01',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'maria@example.com']);
    }

    public function test_user_type_cannot_be_tampered(): void
    {
        $this->post('/register', $this->validPayload([
            'user_type' => 'admin', // hostile extra field
        ]));

        $this->assertSame(
            'resident',
            User::where('email', 'maria@example.com')->first()->user_type,
        );
    }

    public function test_registration_is_audit_logged(): void
    {
        $this->post('/register', $this->validPayload());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.registration_submitted',
            'user_email' => 'maria@example.com',
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', $this->validPayload(['email' => 'taken@example.com']))
            // Validation errors live in the named 'register' bag so they never
            // bleed into the login form's own fields on the same page.
            ->assertSessionHasErrorsIn('register', ['email']);

        $this->assertSame(1, User::count());
    }

    public function test_matching_existing_resident_record_is_not_automatically_linked(): void
    {
        $purok = Purok::factory()->create();
        $existing = Resident::factory()->create([
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'birth_date' => '1995-06-15',
            'purok_id' => $purok->id,
        ]);

        $this->post('/register', $this->validPayload());

        // Identity matching is only a claim for staff review; it does not
        // mutate or attach the existing authoritative resident row.
        $this->assertSame(1, Resident::where('first_name', 'Maria')->count());
        $this->assertNull($existing->fresh()->user_id);
        $this->assertDatabaseHas('resident_applications', [
            'email' => 'maria@example.com',
            'status' => 'Pending',
        ]);
    }

    public function test_a_resident_record_that_already_has_an_account_is_not_reclaimed(): void
    {
        $owner = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $existing = Resident::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Dela Cruz',
            'birth_date' => '1995-06-15',
            'user_id' => $owner->id,
        ]);

        $this->post('/register', $this->validPayload());

        // The existing account/profile remains untouched; only a pending
        // application claim is created for the new applicant.
        $this->assertSame(1, Resident::where('first_name', 'Maria')->count());
        $this->assertSame($owner->id, $existing->fresh()->user_id);
        $this->assertDatabaseHas('resident_applications', [
            'email' => 'maria@example.com',
            'status' => 'Pending',
        ]);
    }

    public function test_registration_rejects_letters_in_phone_numbers_and_foreign_ids(): void
    {
        $this->post('/register', $this->validPayload([
            'phone_number' => 'phone-number',
            'purok_id' => 'not-an-id',
            'household_id' => 'not-an-id',
        ]))->assertSessionHasErrorsIn('register', [
            'phone_number',
            'purok_id',
            'household_id',
        ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_pending_residents_cannot_request_a_reset_code(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'pending']);

        // Told why, and held on the email step — no code and no code screen.
        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        \Illuminate\Support\Facades\Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_the_create_account_dialog_submits_successfully_with_json(): void
    {
        // The login-page dialog posts with Accept: application/json. The
        // controller used to declare RedirectResponse only, so this path
        // threw a TypeError and every sign-up attempt came back as a 500.
        $this->postJson('/register', $this->validPayload())
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('users', ['email' => 'maria@example.com', 'status' => 'pending']);
        $this->assertDatabaseHas('resident_applications', ['email' => 'maria@example.com']);
    }

    public function test_the_dialog_receives_validation_errors_as_json_422(): void
    {
        $this->postJson('/register', $this->validPayload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_sign_up_masks_passwords_behind_toggles(): void
    {
        // Both password fields mask by default; only the confirmation keeps
        // a show/hide toggle so a typo can still be caught before submitting.
        $login = $this->get('/login')->getContent();
        $this->assertStringContainsString('id="register-password" type="password"', $login);
        $this->assertStringNotContainsString('data-password-toggle="register-password"', $login);
        $this->assertStringContainsString('data-password-toggle="register-password_confirmation"', $login);

        // Same rule on the standalone no-script sign-up page.
        $page = $this->get('/register')->getContent();
        $this->assertStringContainsString('id="password" type="password"', $page);
        $this->assertStringNotContainsString('data-password-toggle="password"', $page);
        $this->assertStringContainsString('data-password-toggle="password_confirmation"', $page);
    }

    public function test_phone_fields_offer_a_numeric_keypad_and_a_format_placeholder(): void
    {
        // Mobile: tapping the field raises the digits-only keyboard, and the
        // placeholder shows the format instead of a real-looking number.
        foreach (['/login', '/register'] as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();

            $this->assertStringContainsString('placeholder="09XX XXX XXXX"', $html, $uri);
            $this->assertStringContainsString('inputmode="numeric"', $html, $uri);
            $this->assertStringNotContainsString('placeholder="09171234567"', $html, $uri);
        }
    }
}
