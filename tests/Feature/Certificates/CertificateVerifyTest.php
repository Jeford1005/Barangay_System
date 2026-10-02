<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Resident;
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

    public function test_versioned_token_format_length_and_rotation(): void
    {
        $control = 'CTL-2026-0001';
        $token = CertificateVerification::token($control);

        // Versioned format: `v1` prefix + 14 hex, still 16 chars total.
        $this->assertSame(16, strlen($token));
        $this->assertSame(CertificateVerification::TOKEN_LENGTH, strlen($token));
        $this->assertMatchesRegularExpression('/^v1[0-9a-f]{14}$/', $token);
        $this->assertTrue(CertificateVerification::isValid($control, $token));
        $this->assertTrue(CertificateVerification::isValid($control, strtoupper($token)));

        // An unknown version never validates.
        $this->assertFalse(CertificateVerification::isValid($control, 'v2'.substr($token, 2)));

        // Legacy pre-versioning tokens (bare 16 hex over the raw number)
        // are honored while old printed stock may still be in the wild.
        $legacy = substr(hash_hmac('sha256', $control, (string) config('app.key')), 0, 16);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $legacy);
        $this->assertTrue(CertificateVerification::isValid($control, $legacy));

        // A superseded app key in APP_PREVIOUS_KEYS keeps already-printed
        // QRs verifying after a key roll.
        config()->set('app.previous_keys', ['superseded-key']);
        $rolled = 'v1'.substr(hash_hmac('sha256', 'v1:'.$control, 'superseded-key'), 0, 14);
        $this->assertTrue(CertificateVerification::isValid($control, $rolled));
    }

    public function test_legacy_token_verifies_end_to_end(): void
    {
        $issuance = CertificateIssuance::factory()->create(['status' => 'Issued']);
        $legacy = substr(
            hash_hmac('sha256', $issuance->control_number, (string) config('app.key')),
            0,
            16
        );

        $this->get(route('certificates.verify', [
            'control_number' => $issuance->control_number,
            'token' => $legacy,
        ]))
            ->assertOk()
            ->assertSee('VALID CERTIFICATE');
    }

    public function test_certificate_print_back_link_follows_the_viewer(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $issuance = CertificateIssuance::factory()->create(['status' => 'Issued']);

        $officeHtml = $this->actingAs($admin)
            ->get(route('certificates.print', $issuance))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Back to certificates', $officeHtml);
        $this->assertStringContainsString(route('certificates.index'), $officeHtml);

        // Resident viewer: their own approved request renders the same
        // sheet, but Back must return to their request list — the office
        // index would 403 for them.
        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $resident = Resident::factory()->create(['user_id' => $user->id]);
        $mine = CertificateIssuance::factory()->create(['resident_id' => $resident->id, 'status' => 'Issued']);
        $request = CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $mine->document_id,
            'purpose' => 'Viewer probe',
            'copies' => 1,
            'status' => 'Approved',
        ]);
        $request->issuance_id = $mine->id;
        $request->save();

        $residentHtml = $this->actingAs($user)
            ->get(route('resident.requests.certificate', $request))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Back to my requests', $residentHtml);
        $this->assertStringContainsString(route('resident.requests'), $residentHtml);
        $this->assertStringNotContainsString(route('certificates.index'), $residentHtml);
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
