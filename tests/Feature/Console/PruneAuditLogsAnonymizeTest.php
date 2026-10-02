<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAuditLogsAnonymizeTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(string $event, int $daysAgo, array $properties): AuditLog
    {
        // Real actor ids (not nulls) so the anonymize pass has identifiers
        // to actually redact — user_id carries a users FK, so it needs a
        // genuine row.
        $actorId = User::factory()->create()->id;

        return AuditLog::create([
            'occurred_at' => now()->subDays($daysAgo),
            'user_id' => $actorId,
            'user_email' => 'subject@example.com',
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'actor_email' => 'actor@example.com',
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => null,
            'event' => $event,
            'ip_address' => '203.0.113.9',
            'user_agent' => 'test-agent',
            'properties' => $properties,
        ]);
    }

    private function piiProperties(): array
    {
        return [
            'contact_email' => 'juan@example.com',
            'client_ip' => '203.0.113.7',
            'mobile' => '09171234567',
            'api_key' => 'Abcdef1234567890abcdef1234567890',
            'note' => 'Called from 203.0.113.7 about juan@example.com',
            'resident_name' => 'Juan Dela Cruz',
            'case_number' => 'BLT-2026-0001',
            // Long dashed business code: must survive the token scrub.
            'control_number' => 'CERTIFICATE-2026-ABCDEFGHIJ-0001',
            'resident_id' => 42,
            'actor_id' => 7,
            'actor_email' => 'actor@example.com',
            'reason' => 'Valid correction request',
            'filters' => ['status' => 'Pending'],
            'nested' => ['backup_contact' => '+63 917 123 4567'],
        ];
    }

    public function test_prune_anonymizes_pii_in_retained_rows_but_keeps_structure(): void
    {
        $log = $this->makeLog('admin.audit_viewed', 10, $this->piiProperties());

        $this->artisan('audit:prune-logs')->assertSuccessful();

        $log->refresh();
        $properties = $log->properties;

        // Same keys, same nesting — only values changed.
        $this->assertSame(
            ['contact_email', 'client_ip', 'mobile', 'api_key', 'note', 'resident_name', 'case_number', 'control_number', 'resident_id', 'actor_id', 'actor_email', 'reason', 'filters', 'nested'],
            array_keys($properties)
        );

        // PII redacted…
        $this->assertSame(AuditLog::REDACTED, $properties['contact_email']);
        $this->assertSame(AuditLog::REDACTED, $properties['client_ip']);
        $this->assertSame(AuditLog::REDACTED, $properties['mobile']);
        $this->assertSame(AuditLog::REDACTED, $properties['api_key']);
        $this->assertSame(AuditLog::REDACTED, $properties['actor_id']);
        $this->assertSame(AuditLog::REDACTED, $properties['actor_email']);
        $this->assertSame(AuditLog::REDACTED, $properties['nested']['backup_contact']);
        $this->assertStringNotContainsString('juan@example.com', $properties['note']);
        $this->assertStringNotContainsString('203.0.113.7', $properties['note']);
        $this->assertStringContainsString('Called from', $properties['note']);

        // …structure and non-PII intact — including the long dashed
        // control number, which is an identifier, not a token.
        $this->assertSame('Juan Dela Cruz', $properties['resident_name']);
        $this->assertSame('BLT-2026-0001', $properties['case_number']);
        $this->assertSame('CERTIFICATE-2026-ABCDEFGHIJ-0001', $properties['control_number']);
        $this->assertSame(42, $properties['resident_id']);
        $this->assertSame('Valid correction request', $properties['reason']);
        $this->assertSame(['status' => 'Pending'], $properties['filters']);

        // Event and timestamps intact; identifying columns redacted.
        $this->assertSame('admin.audit_viewed', $log->event);
        $this->assertNull($log->user_id);
        $this->assertNull($log->actor_id);
        $this->assertSame(AuditLog::REDACTED, $log->user_email);
        $this->assertSame(AuditLog::REDACTED, $log->actor_email);
        $this->assertSame(AuditLog::REDACTED, $log->ip_address);
        $this->assertNotNull($log->occurred_at);
    }

    public function test_prune_still_deletes_rows_older_than_retention(): void
    {
        $old = $this->makeLog('admin.audit_viewed', 400, ['note' => 'ancient']);
        $kept = $this->makeLog('admin.audit_viewed', 10, $this->piiProperties());

        $this->artisan('audit:prune-logs')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $kept->id]);
        $this->assertSame(AuditLog::REDACTED, $kept->refresh()->properties['contact_email']);
    }

    public function test_dry_run_reports_both_counts_without_changing_anything(): void
    {
        $this->makeLog('admin.audit_viewed', 400, ['note' => 'ancient']);
        $log = $this->makeLog('admin.audit_viewed', 10, $this->piiProperties());

        $this->artisan('audit:prune-logs', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Would delete 1 audit_logs row(s)')
            ->expectsOutputToContain('Would anonymize 1 retained audit_logs row(s)');

        $this->assertSame(2, AuditLog::count());
        $this->assertSame('juan@example.com', $log->refresh()->properties['contact_email']);
    }

    public function test_skip_anonymize_deletes_without_rewriting_retained_rows(): void
    {
        $this->makeLog('admin.audit_viewed', 400, ['note' => 'ancient']);
        $log = $this->makeLog('admin.audit_viewed', 10, $this->piiProperties());

        $this->artisan('audit:prune-logs', ['--skip-anonymize' => true])->assertSuccessful();

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('juan@example.com', $log->refresh()->properties['contact_email']);
    }

    public function test_retention_floor_still_refuses_small_windows(): void
    {
        $this->artisan('audit:prune-logs', ['--days' => 7])->assertFailed();
        $this->assertSame(0, AuditLog::count());
    }

    public function test_anonymize_never_eats_long_control_numbers_or_codes(): void
    {
        // Long dashed identifiers (control/case numbers, invoice refs) are
        // shaped nothing like opaque secrets and must survive verbatim —
        // including at 32+ chars, where the old token heuristic ate them.
        $codes = [
            'CERTIFICATE-2026-ABCDEFGHIJ-0001',
            'BLTR-2026-ABCDEFGHIJ-0001-EXTRAX',
            'invoice-ref-2026-abcdef-00001-xyz',
        ];

        foreach ($codes as $code) {
            $this->assertGreaterThanOrEqual(32, strlen($code), 'fixture must reach token length');

            $scrubbed = AuditLog::anonymizeProperties(['control_number' => $code]);

            $this->assertSame($code, $scrubbed['control_number']);
        }

        // …while genuine token shapes are still redacted.
        $this->assertSame(
            AuditLog::REDACTED,
            AuditLog::anonymizeProperties(['api_key' => 'Abcdef1234567890abcdef1234567890'])['api_key']
        );
        $this->assertSame(
            AuditLog::REDACTED,
            AuditLog::anonymizeProperties(['session' => '550e8400-e29b-41d4-a716-446655440000'])['session']
        );
    }

    public function test_ipv6_scrub_handles_compressed_notation_without_mangling(): void
    {
        // Real compressed addresses are redacted whole, surroundings intact.
        foreach (['::1' => 'ping [REDACTED] ok', 'fe80::' => 'ping [REDACTED] ok', '2001:db8::1' => 'ping [REDACTED] ok', '::ffff:192.0.2.1' => 'ping [REDACTED] ok'] as $ip => $expected) {
            $scrubbed = AuditLog::anonymizeProperties(['note' => "ping {$ip} ok"]);

            $this->assertSame($expected, $scrubbed['note']);
        }

        // A bare "::" with no hex digit on either side is prose, not an
        // address — it must survive verbatim.
        foreach (['see :: below', 'ratio 1 :: 2 ok'] as $prose) {
            $this->assertSame(
                $prose,
                AuditLog::anonymizeProperties(['note' => $prose])['note']
            );
        }
    }

    public function test_anonymize_preserves_audit_timestamps(): void
    {
        $log = $this->makeLog('admin.audit_viewed', 10, $this->piiProperties());
        $stamped = now()->subHours(2)->toDateTimeString();

        AuditLog::whereKey($log->id)->update(['created_at' => $stamped, 'updated_at' => $stamped]);

        $this->artisan('audit:prune-logs')->assertSuccessful();

        $log->refresh();

        $this->assertSame($stamped, $log->created_at->toDateTimeString());
        $this->assertSame($stamped, $log->updated_at->toDateTimeString());
    }

    public function test_anonymize_redacts_email_subject_labels(): void
    {
        // Password-reset rows carry the visitor's address as subject_label.
        $log = $this->makeLog('admin.audit_viewed', 10, ['reason' => 'ok']);
        $log->subject_type = 'user';
        $log->subject_label = 'visitor@example.com';
        $log->timestamps = false;
        $log->save();

        $this->artisan('audit:prune-logs')->assertSuccessful();

        $this->assertSame(AuditLog::REDACTED, $log->refresh()->subject_label);
    }
}
