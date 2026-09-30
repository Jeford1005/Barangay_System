<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * One continuous password-reset journey — request, masked hint, reset,
 * audit trail, sign-in with the new password, and single-use enforcement.
 * PasswordResetTest pins each step in isolation; this pins the whole flow
 * end to end exactly as a visitor walks it.
 */
class PasswordResetEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_request_a_code_reset_and_sign_in(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'user_type' => 'admin',
            'email' => 'juan.delacruz@gmail.com',
            'password' => 'old-password',
        ]);

        // 1. Request a code — the visitor advances to the code screen.
        // The address travels in the session, never in the URL (no PII in Location).
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.reset'));
        $this->assertSame($user->email, session('password_reset.email'));

        $code = null;
        Notification::assertSentTo($user, ResetPasswordCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->assertNotNull($code);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        // 2. The code screen shows a masked hint, never the raw address.
        $this->get(route('password.reset', ['email' => $user->email]))
            ->assertOk()
            ->assertSee('ju***********@gmail.com')
            ->assertDontSee('We sent a 6-character code to juan.delacruz@gmail.com', false);

        // 3. Reset with the code.
        $this->post(route('password.update'), [
            'email' => $user->email,
            'code' => $code,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        // 4. The audit trail records the request and the completion.
        $this->assertDatabaseHas('audit_logs', ['event' => 'password_reset.code_requested']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'password_reset.completed']);

        // 5. The new password signs the visitor in; the code is single-use.
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'brand-new-password',
            'user_type' => 'office',
        ]);
        $this->assertAuthenticated();

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('password.update'), [
            'email' => $user->email,
            'code' => $code,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }
}
