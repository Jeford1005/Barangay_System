<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateIssuance;
use App\Models\User;
use App\Services\CertificateVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_issuance_verifies_as_guest(): void
    {
        $issuance = CertificateIssuance::factory()->create(['status' => 'Issued']);

        // No actingAs: the verify page is public.
        $this->get(CertificateVerification::url($issuance->control_number))
            ->assertOk()
            ->assertSee('VALID CERTIFICATE')
            ->assertSee($issuance->control_number)
            ->assertSee($issuance->document->title)
            // The public result must not leak resident PII.
            ->assertDontSee($issuance->resident->full_name);
    }

    public function test_forged_token_is_rejected_without_leaking_existence(): void
    {
        $issuance = CertificateIssuance::factory()->create(['status' => 'Issued']);
        $real = CertificateVerification::token($issuance->control_number);
        $forged = substr($real, 0, -1).($real[-1] === '0' ? '1' : '0');

        $this->get(route('certificates.verify', [
            'control_number' => $issuance->control_number,
            'token' => $forged,
        ]))
            ->assertOk()
            ->assertSee('INVALID VERIFICATION CODE')
            ->assertDontSee('VALID CERTIFICATE')
            ->assertDontSee('VOID CERTIFICATE');
    }

    public function test_malformed_token_is_rejected(): void
    {
        $issuance = CertificateIssuance::factory()->create(['status' => 'Issued']);

        $this->get(route('certificates.verify', [
            'control_number' => $issuance->control_number,
            'token' => 'not-a-token!!',
        ]))
            ->assertOk()
            ->assertSee('INVALID VERIFICATION CODE');
    }

    public function test_voided_issuance_shows_void(): void
    {
        $issuance = CertificateIssuance::factory()->create(['status' => 'Voided']);

        $this->get(CertificateVerification::url($issuance->control_number))
            ->assertOk()
            ->assertSee('VOID CERTIFICATE')
            ->assertSee($issuance->control_number)
            ->assertDontSee('VALID CERTIFICATE');
    }

    public function test_unknown_number_shows_not_found_with_200(): void
    {
        $control = 'ZZZ-2099-0001';

        $this->get(route('certificates.verify', [
            'control_number' => $control,
            'token' => CertificateVerification::token($control),
        ]))
            ->assertOk()
            ->assertSee('CERTIFICATE NOT FOUND')
            ->assertSee($control);
    }

    public function test_print_html_contains_the_qr_block_and_verify_url(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $issuance = CertificateIssuance::factory()->create(['status' => 'Issued']);
        $verifyUrl = CertificateVerification::url($issuance->control_number);

        $html = $this->actingAs($admin)
            ->get(route('certificates.print', $issuance))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="cert-qr"', $html);
        $this->assertStringContainsString('data-verify-url', $html);
        $this->assertStringContainsString($verifyUrl, $html);
        $this->assertStringContainsString('Scan to verify', $html);
    }
}
