<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_root_url_routes_by_role(): void
    {
        // Guests see the sign-in screen served at the bare domain, so the
        // address bar never exposes an internal path.
        $this->get('/')->assertOk()->assertSee('Sign in');

        // Signed-in admins land on their workspace.
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_guests_are_redirected_to_login_from_protected_pages(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/puroks')->assertRedirect('/login');
        $this->get('/residents')->assertRedirect('/login');
        $this->get('/households')->assertRedirect('/login');
    }

    public function test_admin_can_authenticate(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'user_type' => 'office',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_fails_when_user_type_does_not_match_the_account(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'user_type' => 'resident',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('password');
    }

    /**
     * The office tab covers both office roles, so the stored role — not the tab
     * — still decides what each account reaches. These pin both halves of that:
     * either office role gets in through the one tab, and neither tab reaches
     * across the office/resident divide.
     */
    public function test_the_office_tab_admits_both_office_roles(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['user_type' => $role]);

            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'user_type' => 'office',
            ]);

            $this->assertAuthenticatedAs($user, null, "a {$role} must get in through the office tab");
            $response->assertRedirect(route('dashboard'));

            $this->post('/logout');
        }
    }

    public function test_the_office_tab_does_not_admit_a_resident_account(): void
    {
        $user = User::factory()->create(['user_type' => 'resident']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'user_type' => 'office',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('password');
    }

    public function test_the_resident_tab_does_not_admit_an_office_account(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['user_type' => $role]);

            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'user_type' => 'resident',
            ]);

            $this->assertGuest();
            $response->assertSessionHasErrors('password');
        }
    }

    public function test_a_staff_account_still_lands_on_the_dashboard_and_keeps_its_permissions(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'user_type' => 'office',
        ])->assertRedirect(route('dashboard'));

        // The tab is only a door. What the account may then do is still decided
        // by its stored role, so staff keep the limited set and not the rest.
        $this->assertTrue($user->hasPermission('residents.view'));
        $this->assertFalse($user->hasPermission('users.manage'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'user_type' => 'office',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('password');
    }

    public function test_invalid_login_message_is_shown_below_password_without_replaying_it(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'user_type' => 'office',
        ])->assertRedirect('/');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Invalid email or password.')
            ->assertSee('data-icon="x-circle"', false)
            // The rejection script must empty the field, take focus, and clear
            // the error state on a timer. Asserted by behaviour marker rather
            // than by the exact source, so a rename does not break the test.
            ->assertSee("field.value = ''", false)
            ->assertSee('field.focus()', false)
            ->assertSee('setTimeout', false)
            ->assertSee('classList.remove', false)
            ->assertSee('id="password"', false)
            ->assertDontSee('wrong-password');
    }

    public function test_login_page_offers_office_and_resident_as_tabs(): void
    {
        $response = $this->get('/login')->assertOk();

        $response->assertSee('role="tablist"', false)
            ->assertSee('aria-label="Signing in as"', false)
            ->assertSee('role="tab"', false);

        // The switcher owns the field, so the page must not also carry a
        // radio group for the same choice.
        $response->assertDontSee('type="radio"', false);

        // Administrators and staff share one tab, so the two groups are offered
        // and exactly one is selected, with the field primed to that same group.
        $this->assertSame(
            ['office', 'resident'],
            $this->roleOptions($response->getContent())
        );
        $this->assertSame(['office'], $this->selectedRoleOptions($response->getContent()));
        $this->assertSame('office', $this->primedRoleField($response->getContent()));
    }

    public function test_login_page_rechecks_the_submitted_role_after_a_failure(): void
    {
        $user = User::factory()->create(['user_type' => 'staff']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'user_type' => 'office',
        ]);

        // One request only: the flashed input is available to the next request
        // alone, so a second GET would already show the default again.
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertSame(
            ['office'],
            $this->selectedRoleOptions($html),
            'The rejected role must come back selected.'
        );
        $this->assertSame('office', $this->primedRoleField($html));
    }

    /**
     * Role values offered as tabs, in document order.
     *
     * @return array<int, string>
     */
    private function roleOptions(string $html): array
    {
        preg_match_all('/data-role-tab="([a-z]+)"/', $html, $matches);

        return $matches[1];
    }

    /**
     * Role values whose tab is marked selected, in document order.
     *
     * @return array<int, string>
     */
    private function selectedRoleOptions(string $html): array
    {
        preg_match_all('/data-role-tab="([a-z]+)"[^>]*aria-selected="true"/', $html, $matches);

        return $matches[1];
    }

    /**
     * The role the hidden field starts on.
     *
     * It deliberately carries no `name` until the script runs, so the
     * <noscript> select is the only control posting `user_type` when
     * JavaScript is unavailable. Naming it here would double-post the role.
     */
    private function primedRoleField(string $html): ?string
    {
        if (! preg_match('/<input type="hidden" id="login-user-type" value="([a-z]+)">/', $html, $matches)) {
            return null;
        }

        return $matches[1];
    }

    public function test_login_page_never_double_posts_the_role(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        // Exactly one control may carry the name, or the server would see two
        // `user_type` fields and have to guess which one the user meant.
        $this->assertSame(
            0,
            preg_match_all('/<input[^>]*name="user_type"/', $html),
            'The switcher must not name the field in markup; the script owns it.'
        );

        $this->assertSame(
            1,
            preg_match_all('/<select[^>]*name="user_type"/', $html),
            'The no-script fallback must be the only named control in the markup.'
        );
    }

    public function test_login_requires_a_valid_user_type(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('user_type');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'user_type' => 'office',
            ]);
        }

        $throttleKey = Str::transliterate(Str::lower($user->email).'|127.0.0.1');

        // Exactly five, not ten. The office tab is tried against both office
        // roles, so a limiter hit per candidate role would burn the budget
        // twice per attempt and lock a real user out after three tries.
        $this->assertSame(5, RateLimiter::attempts($throttleKey));
        $this->assertTrue(RateLimiter::tooManyAttempts($throttleKey, 5));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'user_type' => 'office',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
