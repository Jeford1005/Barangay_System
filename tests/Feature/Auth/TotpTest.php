<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TotpTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_user_can_enroll_and_confirm_with_a_live_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($user)->post(route('two-factor.enroll'))
            ->assertRedirect(route('two-factor.settings'));

        $secret = session('two_factor.pending_secret');
        $this->assertIsString($secret);

        // The provisioning details are text (no QR images): URI plus key.
        $this->actingAs($user)->get(route('two-factor.settings'))
            ->assertOk()
            ->assertSee('otpauth://totp/', false)
            ->assertSee($secret);

        $response = $this->actingAs($user)->post(route('two-factor.confirm'), [
            'code' => app(TotpService::class)->currentCode($secret),
        ]);

        $response->assertRedirect(route('two-factor.settings'));

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertCount(8, session('two_factor.recovery_codes_plain'));
        $this->assertCount(8, $user->remainingRecoveryCodeHashes());
        $this->assertTrue(AuditLog::where('event', 'two_factor.enrolled')->exists());
    }

    public function test_confirm_rejects_a_wrong_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($user)->post(route('two-factor.enroll'));
        $secret = session('two_factor.pending_secret');

        $response = $this->actingAs($user)->post(route('two-factor.confirm'), [
            'code' => $this->invalidCode($secret),
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
        $this->assertTrue(AuditLog::where('event', 'two_factor.verification_failed')->exists());
    }

    public function test_login_challenges_a_totp_enabled_office_account(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $codes = $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('two-factor.challenge'));

        // Password alone must not authenticate while the challenge is open.
        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertOk();

        $this->post(route('two-factor.verify'), ['code' => $codes[0]])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        // Single use: the spent code is burned.
        $this->assertCount(7, $user->refresh()->remainingRecoveryCodeHashes());
    }

    public function test_challenge_accepts_a_live_authenticator_code(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('two-factor.challenge'));

        $code = app(TotpService::class)->currentCode((string) $user->refresh()->two_factor_secret);

        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_challenge_rejects_a_wrong_code_without_logging_in(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ]);

        $secret = (string) $user->refresh()->two_factor_secret;

        $this->post(route('two-factor.verify'), ['code' => $this->invalidCode($secret)])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertTrue(AuditLog::where('event', 'two_factor.verification_failed')->exists());
    }

    public function test_recovery_code_cannot_be_reused(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $codes = $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $login = [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ];

        $this->post('/login', $login)->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])
            ->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->post('/login', $login)->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        // A fresh code still works after the spent one is refused.
        $this->post(route('two-factor.verify'), ['code' => $codes[1]])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_resident_login_is_unaffected_and_has_no_totp_surface(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->post('/login', [
            'email' => $resident->email,
            'password' => 'barangay-2026',
            'user_type' => 'resident',
        ])->assertRedirect(route('resident.portal'));

        $this->assertAuthenticatedAs($resident);

        // No challenge step, no pending marker, no account-area access.
        $this->post('/logout');
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->actingAs($resident)->get(route('two-factor.settings'))->assertForbidden();
    }

    public function test_resident_cannot_enroll(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->post(route('two-factor.enroll'))->assertForbidden();
        $this->actingAs($resident)->post(route('two-factor.confirm'), ['code' => '123456'])->assertForbidden();
    }

    public function test_disabled_account_logs_in_with_password_only(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);

        $this->actingAs($user)->delete(route('two-factor.destroy'), [
            'password' => 'barangay-2026',
        ])->assertRedirect(route('two-factor.settings'));

        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
        $this->assertTrue(AuditLog::where('event', 'two_factor.disabled')->exists());
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_disable_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);

        $this->actingAs($user)->delete(route('two-factor.destroy'), [
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
    }

    /**
     * @return array<int, string> plaintext recovery codes shown once at enroll
     */
    private function enableTwoFactorFor(User $user): array
    {
        $this->actingAs($user)->post(route('two-factor.enroll'))
            ->assertRedirect(route('two-factor.settings'));

        $secret = session('two_factor.pending_secret');
        $this->assertIsString($secret);

        $this->actingAs($user)->post(route('two-factor.confirm'), [
            'code' => app(TotpService::class)->currentCode($secret),
        ])->assertRedirect(route('two-factor.settings'));

        $codes = session('two_factor.recovery_codes_plain');
        $this->assertIsArray($codes);

        return $codes;
    }

    /**
     * A code guaranteed to fail verification — the live code with its first
     * digit flipped, re-rolled while it still verifies (clock-skew window).
     */
    private function invalidCode(string $secret): string
    {
        $service = app(TotpService::class);
        $code = $service->currentCode($secret);

        do {
            $code[0] = $code[0] === '0' ? '1' : '0';
            // Step the mutation on so a repeated flip cannot loop forever.
            $code = substr($code, 1).$code[0];
        } while ($service->verify($secret, $code));

        return $code;
    }
}
