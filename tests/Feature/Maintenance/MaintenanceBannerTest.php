<?php

namespace Tests\Feature\Maintenance;

use App\Models\CertificateIssuance;
use App\Models\User;
use App\Services\CertificateVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MaintenanceBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_banner_renders_when_off(): void
    {
        config()->set('app.maintenance_notice.message', null);

        $this->actingAs(User::factory()->create(['user_type' => 'admin']))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('maintenance-notice', false)
            ->assertDontSee('maintenance-active', false);
    }

    public function test_notice_state_renders_dismissible_banner(): void
    {
        config()->set('app.maintenance_notice.message', 'Upgrades Sunday 10PM.');
        config()->set('app.maintenance_notice.from', null);
        config()->set('app.maintenance_notice.to', null);

        $this->actingAs(User::factory()->create(['user_type' => 'admin']))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Upgrades Sunday 10PM.', false)
            ->assertSee('Scheduled maintenance', false)
            ->assertSee('data-dismiss-maintenance', false)
            ->assertDontSee('maintenance-active', false);
    }

    public function test_notice_state_shows_scheduled_window(): void
    {
        config()->set('app.maintenance_notice.message', 'Upgrades Sunday 10PM.');
        config()->set('app.maintenance_notice.from', 'Sunday 10:00 PM');
        config()->set('app.maintenance_notice.to', 'Sunday 11:00 PM');

        $this->actingAs(User::factory()->create(['user_type' => 'admin']))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Scheduled maintenance', false)
            ->assertSee('Sunday 10:00 PM', false)
            ->assertSee('Sunday 11:00 PM', false)
            ->assertSee('data-dismiss-maintenance', false);
    }

    public function test_notice_banner_renders_on_guest_sign_in_and_verify_pages(): void
    {
        config()->set('app.maintenance_notice.message', 'Upgrades Sunday 10PM.');
        config()->set('app.maintenance_notice.from', null);
        config()->set('app.maintenance_notice.to', null);

        // Guest entry point: the sign-in page at / sits outside the app
        // layout, so the banner is mounted there explicitly.
        $this->get('/')
            ->assertOk()
            ->assertSee('Scheduled maintenance', false)
            ->assertSee('maintenance-notice', false);

        // Public certificate verification: likewise outside the layout.
        $issuance = CertificateIssuance::factory()->create(['status' => 'Issued']);

        $this->get(CertificateVerification::url($issuance->control_number))
            ->assertOk()
            ->assertSee('maintenance-notice', false)
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_active_state_is_never_dismissible(): void
    {
        config()->set('app.maintenance_notice.message', 'Upgrades Sunday 10PM.');

        Artisan::call('down');

        try {
            $this->blade('<x-maintenance-banner />')
                ->assertSee('System under maintenance', false)
                ->assertSee('maintenance-active', false)
                ->assertDontSee('data-dismiss-maintenance', false);
        } finally {
            Artisan::call('up');
        }
    }

    public function test_secret_bypass_still_reaches_the_app_and_sees_active_banner(): void
    {
        config()->set('app.maintenance_notice.message', 'Upgrades Sunday 10PM.');

        Artisan::call('down', ['--secret' => 'test-bypass-secret']);

        try {
            // Without the bypass secret the public site stays down.
            $this->get('/dashboard')->assertStatus(503);

            // Visiting the secret path mints the bypass cookie…
            $secretResponse = $this->get('/test-bypass-secret');
            $secretResponse->assertRedirect();

            $cookie = null;

            foreach ($secretResponse->headers->getCookies() as $candidate) {
                if ($candidate->getName() === 'laravel_maintenance') {
                    $cookie = $candidate;

                    break;
                }
            }

            $this->assertNotNull($cookie, 'Expected the secret path to mint a laravel_maintenance cookie.');

            // …which carries the session past maintenance mode into the app,
            // where the non-dismissible active banner is waiting. The
            // Set-Cookie value is already encrypted by the app, so it must
            // travel back unencrypted (exactly as a browser would resend it).
            $this->actingAs(User::factory()->create(['user_type' => 'admin']))
                ->withUnencryptedCookie('laravel_maintenance', $cookie->getValue())
                ->get('/dashboard')
                ->assertOk()
                ->assertSee('System under maintenance', false)
                ->assertSee('maintenance-active', false)
                ->assertDontSee('data-dismiss-maintenance', false);
        } finally {
            Artisan::call('up');
        }
    }
}
