<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TotpTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_user_can_enroll_and_confirm_with_a_live_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'barangay-2026'])
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

    public function test_disable_form_collects_a_live_factor_code(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'barangay-2026']);
        $secret = session('two_factor.pending_secret');
        $this->actingAs($user)->post(route('two-factor.confirm'), [
            'code' => app(TotpService::class)->currentCode($secret),
        ]);

        // Regression: destroy() requires a live TOTP-or-recovery code, so
        // the form must actually collect one.
        $this->actingAs($user)->get(route('two-factor.settings'))
            ->assertOk()
            ->assertSee('id="two-factor-disable-code"', false);
    }

    public function test_confirm_rejects_a_wrong_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'barangay-2026']);
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
            'code' => app(TotpService::class)->currentCode((string) $user->refresh()->two_factor_secret),
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
            'code' => app(TotpService::class)->currentCode((string) $user->refresh()->two_factor_secret),
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
    }

    public function test_enroll_requires_the_current_password(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($user)->post(route('two-factor.enroll'))
            ->assertSessionHasErrors('password');
        $this->assertNull(session('two_factor.pending_secret'));

        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'not-the-password'])
            ->assertSessionHasErrors('password');
        $this->assertNull(session('two_factor.pending_secret'));

        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'barangay-2026'])
            ->assertRedirect(route('two-factor.settings'));

        $this->assertIsString(session('two_factor.pending_secret'));
        $this->assertSame($user->id, session('two_factor.enroll_user_id'));
        $this->assertIsInt(session('two_factor.enroll_started_at'));
    }

    public function test_confirm_rejects_when_two_factor_is_already_enabled(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);

        $before = (string) $user->refresh()->two_factor_secret;

        // A confirm call must never silently overwrite live 2FA.
        $this->actingAs($user)->post(route('two-factor.confirm'), [
            'code' => app(TotpService::class)->currentCode($before),
        ])->assertRedirect(route('two-factor.settings'));

        $this->assertSame($before, (string) $user->refresh()->two_factor_secret);
        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
        $this->assertNull(session('two_factor.pending_secret'));
    }

    public function test_confirm_rejects_an_expired_pending_secret(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'barangay-2026']);
        $secret = session('two_factor.pending_secret');
        $this->assertIsString($secret);

        $response = $this->actingAs($user)->withSession([
            'two_factor.enroll_started_at' => now()->subMinutes(16)->timestamp,
        ])->post(route('two-factor.confirm'), [
            'code' => app(TotpService::class)->currentCode($secret),
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
        $this->assertNull(session('two_factor.pending_secret'));
    }

    public function test_confirm_rejects_a_pending_secret_bound_to_another_user(): void
    {
        $userA = User::factory()->create(['user_type' => 'staff']);
        $userB = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($userA)->post(route('two-factor.enroll'), ['password' => 'barangay-2026']);
        $secret = session('two_factor.pending_secret');
        $this->assertIsString($secret);

        // Same browser session, now signed in as someone else.
        $this->actingAs($userB)->post(route('two-factor.confirm'), [
            'code' => app(TotpService::class)->currentCode($secret),
        ])->assertSessionHasErrors('code');

        $this->assertFalse($userA->refresh()->hasTwoFactorEnabled());
        $this->assertFalse($userB->refresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_rejects_a_tampered_pending_secret(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'barangay-2026']);

        $this->actingAs($user)->withSession([
            'two_factor.pending_secret' => '!!!!TAMPERED!!!!',
        ])->post(route('two-factor.confirm'), [
            'code' => '123456',
        ])->assertSessionHasErrors('code');

        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_throttle_returns_a_validation_error(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'barangay-2026']);
        $secret = session('two_factor.pending_secret');
        $this->assertIsString($secret);

        $bad = $this->invalidCode($secret);

        // Five guesses burn the budget; the sixth must still redirect back
        // with errors (ValidationException), never 500.
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $bad])
                ->assertStatus(302)
                ->assertSessionHasErrors('code');
        }

        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
    }

    public function test_challenge_throttle_returns_a_validation_error(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('two-factor.challenge'));

        $bad = $this->invalidCode((string) $user->refresh()->two_factor_secret);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('two-factor.verify'), ['code' => $bad])
                ->assertStatus(302)
                ->assertSessionHasErrors('code');
        }

        $this->assertGuest();
    }

    public function test_challenge_rejects_a_reused_authenticator_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('two-factor.challenge'));

        $service = app(TotpService::class);
        $secret = (string) $user->refresh()->two_factor_secret;
        $scope = 'two-factor-totp:challenge:'.$user->id;

        // Burn the neighbouring steps too, so a step boundary ticking over
        // mid-test cannot turn this into a false pass.
        $step = $service->currentStep();
        foreach ([$step - 1, $step, $step + 1] as $consumed) {
            Cache::put(
                TotpService::usedStepCacheKey($scope, $consumed),
                true,
                now()->addMinutes(5),
            );
        }

        $this->post(route('two-factor.verify'), ['code' => $service->currentCode($secret)])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_challenge_is_blocked_once_the_account_is_suspended(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('two-factor.challenge'));

        $user->forceFill(['suspended_at' => now(), 'suspended_by' => $user->id])->save();

        $code = app(TotpService::class)->currentCode((string) $user->refresh()->two_factor_secret);

        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull(session('two_factor.pending_user_id'));
        $this->assertNull(session('two_factor.pending_remember'));
    }

    public function test_challenge_is_blocked_once_the_account_is_unapproved(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('two-factor.challenge'));

        $user->forceFill(['status' => 'pending'])->save();

        $code = app(TotpService::class)->currentCode((string) $user->refresh()->two_factor_secret);

        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull(session('two_factor.pending_user_id'));
        $this->assertNull(session('two_factor.pending_remember'));
    }

    public function test_verify_clears_both_pending_markers_for_an_unknown_user(): void
    {
        $this->withSession([
            'two_factor.pending_user_id' => 999999,
            'two_factor.pending_remember' => true,
        ])->post(route('two-factor.verify'), ['code' => '123456'])
            ->assertRedirect(route('login'));

        $this->assertNull(session('two_factor.pending_user_id'));
        $this->assertNull(session('two_factor.pending_remember'));
    }

    public function test_disable_requires_a_factor_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);

        $this->actingAs($user)->delete(route('two-factor.destroy'), [
            'password' => 'barangay-2026',
        ])->assertSessionHasErrors('code');

        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
    }

    public function test_disable_rejects_a_wrong_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);

        $secret = (string) $user->refresh()->two_factor_secret;

        $this->actingAs($user)->delete(route('two-factor.destroy'), [
            'password' => 'barangay-2026',
            'code' => $this->invalidCode($secret),
        ])->assertSessionHasErrors('code');

        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
    }

    public function test_disable_accepts_a_live_authenticator_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);

        $code = app(TotpService::class)->currentCode((string) $user->refresh()->two_factor_secret);

        $this->actingAs($user)->delete(route('two-factor.destroy'), [
            'password' => 'barangay-2026',
            'code' => $code,
        ])->assertRedirect(route('two-factor.settings'));

        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
        $this->assertTrue(AuditLog::where('event', 'two_factor.disabled')->exists());
    }

    public function test_disable_accepts_a_recovery_code(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $codes = $this->enableTwoFactorFor($user);

        $this->actingAs($user)->delete(route('two-factor.destroy'), [
            'password' => 'barangay-2026',
            'code' => $codes[0],
        ])->assertRedirect(route('two-factor.settings'));

        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
    }

    public function test_challenge_rejects_an_oversized_code_before_any_hash_check(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);
        $this->enableTwoFactorFor($user);
        $this->post('/logout');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->post(route('two-factor.verify'), ['code' => str_repeat('A', 100)])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_used_time_steps_are_tracked_and_secrets_are_strict(): void
    {
        $service = app(TotpService::class);
        $scope = 'totp-test-scope';

        $this->assertFalse($service->stepAlreadyUsed($scope, 123));
        $service->markStepUsed($scope, 123);
        $this->assertTrue($service->stepAlreadyUsed($scope, 123));
        $this->assertFalse($service->stepAlreadyUsed($scope, 124));
        $this->assertFalse($service->stepAlreadyUsed('another-scope', 123));

        $secret = $service->generateSecret();
        $this->assertTrue(TotpService::isValidSecret($secret));
        $this->assertFalse(TotpService::isValidSecret('!!!!tampered!!!!'));
        $this->assertFalse(TotpService::isValidSecret('ABC'));

        $at = 1700000000;
        $code = $service->currentCode($secret, $at);
        $this->assertSame($service->currentStep($at), $service->matchedStep($secret, $code, $at));
        $this->assertNull($service->matchedStep($secret, 'abcdef', $at));
    }

    /**
     * @return array<int, string> plaintext recovery codes shown once at enroll
     */
    private function enableTwoFactorFor(User $user): array
    {
        $this->actingAs($user)->post(route('two-factor.enroll'), ['password' => 'barangay-2026'])
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
