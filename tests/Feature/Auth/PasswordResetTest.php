<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Request a code for a user and return the generated code.
     */
    private function requestCodeFor(User $user): string
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => $user->email]);

        $code = null;
        Notification::assertSentTo($user, ResetPasswordCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return $code;
    }

    public function test_forgot_password_screen_renders(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Reset your password');
    }

    public function test_reset_code_email_uses_the_branded_template(): void
    {
        $user = User::factory()->create(['user_type' => 'admin', 'name' => 'Juan']);

        $mailable = (new ResetPasswordCodeNotification('AB3456', 15))->toMail($user);

        $html = $mailable->render();

        // The code, recipient name, and expiry appear in the branded layout…
        $this->assertStringContainsString('AB3456', $html);
        $this->assertStringContainsString('Hello Juan', $html);
        $this->assertStringContainsString('15 minutes', $html);

        // …alongside the login page's dark-brand design cues.
        $this->assertStringContainsString('#171717', $html);   // neutral-900 header
        $this->assertStringContainsString('Barangay Management System', $html);

        // The seal is inlined as a data URI. Asserting the asset *path* used to
        // pass while the image was still broken for the recipient, because
        // asset() only builds a URL from APP_URL — http://localhost locally,
        // which resolves on the server and nowhere else.
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('http://localhost', $html);

        // Inline styles only — email clients strip <style> blocks.
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringContainsString('style=', $html);
    }

    public function test_reset_code_email_has_a_plain_text_alternative(): void
    {
        $user = User::factory()->create(['user_type' => 'admin', 'name' => 'Juan']);

        $mailable = (new ResetPasswordCodeNotification('AB3456', 15))->toMail($user);

        // MailMessage registers both parts under view() as html/text keys.
        $this->assertSame('emails.reset-password-code', $mailable->view['html']);
        $this->assertSame('emails.reset-password-code-text', $mailable->view['text']);

        // The text alternative carries the same essentials.
        $text = view('emails.reset-password-code-text', ['code' => 'AB3456', 'expiresInMinutes' => 15, 'userName' => 'Juan'])->render();
        $this->assertStringContainsString('AB3456', $text);
        $this->assertStringContainsString('expires in 15 minutes', $text);
        $this->assertStringNotContainsString('<', str_replace('<<', '', $text)); // no HTML tags in the plain part
    }

    public function test_a_reset_code_is_emailed_and_stored_hashed(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);
        $code = $this->requestCodeFor($user);

        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        $this->assertNotNull($row);
        $this->assertNotSame($code, $row->token, 'the code must never be stored in plain text');
        $this->assertSame(hash('sha256', $code), $row->token);
    }

    public function test_no_code_is_sent_for_unknown_emails(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        // Held on step 1 with the reason, never advanced to a code screen that
        // would wait for an email nothing sent.
        $response->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            "couldn't find an account",
            session('errors')->first('email'),
        );

        // The reason is genuinely rendered on the page they land on, not just
        // flashed into the session.
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee("couldn't find an account");

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_a_mistyped_address_does_not_start_the_cooldown(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect(route('password.request'));

        // The corrected address works straight away — a typo must not cost a
        // 60-second wait, because no code was issued for the wrong one.
        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('password.reset', ['email' => $user->email]));

        Notification::assertSentTimes(ResetPasswordCodeNotification::class, 1);
    }

    public function test_unusable_accounts_are_told_why_and_not_advanced(): void
    {
        Notification::fake();

        $rejected = User::factory()->create(['user_type' => 'resident', 'status' => 'rejected']);

        $suspended = User::factory()->create(['user_type' => 'resident']);
        $suspended->suspended_at = now();
        $suspended->save();

        $this->post('/forgot-password', ['email' => $rejected->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('not approved', session('errors')->first('email'));

        $this->post('/forgot-password', ['email' => $suspended->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('suspended', session('errors')->first('email'));

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_a_new_code_invalidates_the_previous_one(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $firstCode = $this->requestCodeFor($user);

        $this->travel(61)->seconds(); // clear the 60s resend cooldown
        $secondCode = $this->requestCodeFor($user);

        $this->assertNotSame($firstCode, $secondCode);

        // Only one row remains, holding the newest code's hash.
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertSame(hash('sha256', $secondCode), $row->token);
    }

    public function test_immediate_resend_is_blocked_by_the_cooldown(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);

        $this->post('/forgot-password', ['email' => $user->email]);

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        // No second code goes out, and the wait message never confirms the account exists.
        Notification::assertSentTimes(ResetPasswordCodeNotification::class, 1);
        $response->assertRedirect(route('password.reset', ['email' => $user->email]))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Please wait'));
    }

    public function test_cooldown_clears_after_a_minute(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->travel(61)->seconds();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTimes(ResetPasswordCodeNotification::class, 2);
    }

    public function test_reset_screen_shows_a_masked_email_hint(): void
    {
        $response = $this->get('/reset-password?email=juan.delacruz@gmail.com')
            ->assertOk()
            ->assertSee('ju***********@gmail.com'); // ju + 11 stars

        // The visible hint is masked; the raw address only survives in the
        // hidden form field the user's own browser submits.
        $this->assertStringNotContainsString(
            'We sent a 6-character code to juan.delacruz@gmail.com',
            $response->getContent(),
        );
    }

    public function test_successful_reset_clears_the_cooldown(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $code = $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect('/login');

        // A new code can be requested right away after a successful reset.
        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email]);
        Notification::assertSentTimes(ResetPasswordCodeNotification::class, 1);
    }

    public function test_verify_screen_renders_and_prefills_the_email(): void
    {
        $this->get('/reset-password?email=user@example.com')
            ->assertOk()
            ->assertSee('user@example.com');
    }

    public function test_password_can_be_reset_with_a_valid_code(): void
    {
        $user = User::factory()->create(['user_type' => 'admin', 'password' => 'old-password']);

        $code = $this->requestCodeFor($user);

        $this->get('/reset-password')->assertOk();

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect('/login')
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));

        // The code is single-use.
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        // And the new password actually works at login.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'new-secure-password',
            'user_type' => 'office',
        ]);
        $this->assertAuthenticated();
    }

    public function test_reset_fails_with_a_wrong_code(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => 'XXXXXX',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_fails_when_the_code_has_expired(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $code = $this->requestCodeFor($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(20)]); // TTL is 15 minutes

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('code');
    }

    public function test_reset_fails_when_the_email_does_not_match_the_code(): void
    {
        $userA = User::factory()->create(['user_type' => 'admin']);
        $userB = User::factory()->create(['user_type' => 'admin']);

        $code = $this->requestCodeFor($userA);

        $this->post('/reset-password', [
            'email' => $userB->email,
            'code' => $code,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('password', $userB->fresh()->password));
    }

    public function test_new_passwords_must_be_confirmed_and_long_enough(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $code = $this->requestCodeFor($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'long-enough-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
    }

    public function test_code_requests_are_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/forgot-password', ['email' => $user->email]);
        }

        // throttle:5,1 on the route.
        $this->post('/forgot-password', ['email' => $user->email])->assertStatus(429);
    }

    // -----------------------------------------------------------------
    // JSON endpoints powering the login page's forgot-password dialog.
    // -----------------------------------------------------------------

    public function test_json_code_request_returns_the_dialog_payload(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);

        $this->postJson('/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJson([
                'email' => $user->email,
                'cooldown' => 60,
                'expires_in_minutes' => 15,
            ])
            ->assertJsonPath('message', fn (string $message) => str_contains($message, '6-character reset code'));

        Notification::assertSentTo($user, ResetPasswordCodeNotification::class);
    }

    public function test_json_code_request_refuses_unknown_emails(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);

        $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
        $this->travel(61)->seconds();

        // The login dialog reads this as an error and stays on its email step
        // instead of opening the code boxes for an address that sent nothing.
        $this->postJson('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, "couldn't find an account"));

        Notification::assertSentTimes(ResetPasswordCodeNotification::class, 1);
    }

    public function test_json_cooldown_returns_a_wait_message_without_sending(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);

        $this->postJson('/forgot-password', ['email' => $user->email]);

        $this->postJson('/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Please wait'))
            ->assertJsonPath('cooldown', fn (int $cooldown) => $cooldown > 0 && $cooldown <= 60);

        Notification::assertSentTimes(ResetPasswordCodeNotification::class, 1);
    }

    public function test_json_reset_succeeds_and_changes_the_password(): void
    {
        $user = User::factory()->create(['user_type' => 'admin', 'password' => 'old-password']);
        $code = $this->requestCodeFor($user);

        $this->postJson('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'has been reset'));

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_json_reset_rejects_a_bad_code_with_422(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $this->requestCodeFor($user);

        $this->postJson('/reset-password', [
            'email' => $user->email,
            'code' => 'XXXXXX',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_json_reset_validates_the_new_password(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);
        $code = $this->requestCodeFor($user);

        $this->postJson('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_login_page_contains_the_forgot_password_dialog(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('forgot-modal', false)          // the dialog container
            ->assertSee('forgot-code-boxes', false)     // 6-box code input
            ->assertSee('data-open-forgot', false)      // the trigger button
            ->assertSee(route('password.request'));     // no-JS fallback link
    }

    public function test_generated_codes_always_contain_at_least_two_digits(): void
    {
        Notification::fake();

        $user = User::factory()->create(['user_type' => 'admin']);

        // Words like "SYSTEM" are six characters of the code alphabet — the
        // clipboard auto-fill relies on digits making real codes unmistakable.
        foreach (range(1, 25) as $attempt) {
            $this->post('/forgot-password', ['email' => $user->email]);
            $this->travel(61)->seconds(); // clear the resend cooldown
        }

        $codes = [];

        Notification::assertSentTo($user, ResetPasswordCodeNotification::class, function (
            $notification,
        ) use (&$codes) {
            $codes[] = $notification->code;

            return true;
        });

        $this->assertNotEmpty($codes);

        foreach ($codes as $code) {
            $this->assertSame(1, preg_match('/(?=.*\d.*\d)[2-9A-Z]{6}/', $code), "code {$code} lacks two digits");
        }
    }

    public function test_reset_page_ships_the_digit_preferring_code_extractor(): void
    {
        $response = $this->get('/reset-password?email=user@example.com')->assertOk();

        // The strict extractor must prefer digit-bearing tokens over plain
        // words, so a copied sentence containing "SYSTEM" can't fill the boxes.
        $this->assertStringContainsString(
            'token.match(/\\d/g) || []).length >= 2',
            $response->getContent(),
        );
    }
}
