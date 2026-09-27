<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_page_renders(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Email address');
    }

    public function test_a_reset_link_is_emailed_for_a_known_account(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['reset_email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_reset_dialog_reopens_on_the_login_screen_with_the_status(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['reset_email' => $user->email]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('id="reset-modal"', false)
            ->assertSee('data-open-on-load', false)
            ->assertSee(session('status'), false);
    }

    public function test_the_reset_dialog_reopens_with_validation_errors(): void
    {
        $this->from('/login')
            ->post('/forgot-password', ['reset_email' => 'not-an-email'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('reset_email')
            ->assertSessionHas('reset_modal');

        // The dialog reopens and carries the message inside it.
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-open-on-load', false)
            ->assertSee('valid email address', false);
    }

    public function test_the_response_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $known = User::factory()->create();

        $this->post('/forgot-password', ['reset_email' => $known->email])->assertSessionHas('status');
        $knownStatus = session('status');
        session()->forget('status');

        $this->post('/forgot-password', ['reset_email' => 'nobody@example.com'])->assertSessionHas('status');

        $this->assertSame($knownStatus, session('status'));
        Notification::assertSentToTimes($known, ResetPassword::class, 1);
    }

    public function test_a_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->get("/reset-password/{$token}?email={$user->email}")
            ->assertOk()
            ->assertSee('New password');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertRedirect('/login');

        $user->refresh();
        $this->assertTrue(
            auth()->guard('web')->getProvider()->validateCredentials($user, ['password' => 'new-secret-password'])
        );
    }

    public function test_a_bad_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(
            auth()->guard('web')->getProvider()->validateCredentials($user, ['password' => 'password'])
        );
    }
}
