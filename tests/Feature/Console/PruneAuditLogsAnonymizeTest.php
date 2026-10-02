<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAuditLogsAnonymizeTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(string $event, int $daysAgo, array $properties): AuditLog
    {
        return AuditLog::create([
            'occurred_at' => now()->subDays($daysAgo),
            'user_id' => null,
            'user_email' => 'subject@example.com',
            'actor_type' => 'system',
            'actor_id' => 7,
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
            'resident_id' => 42,
            'reason' => 'Valid correction request',
            'filters' => ['status' => 'Pending'],
            'nested' => ['backup_contact' => '+63 917 123 4567'],
        ];
    }

    public function test_prune_anonymizes_pii_in_retained_rows_but_keeps_structure(): void
    {
        $log = $this->makeLog('system.cache_cleared', 10, $this->piiProperties());

        $this->artisan('audit:prune-logs')->assertSuccessful();

        $log->refresh();
        $properties = $log->properties;

        // Same keys, same nesting — only values changed.
        $this->assertSame(
            ['contact_email', 'client_ip', 'mobile', 'api_key', 'note', 'resident_name', 'case_number', 'resident_id', 'reason', 'filters', 'nested'],
            array_keys($properties)
        );

        // PII redacted…
        $this->assertSame(AuditLog::REDACTED, $properties['contact_email']);
        $this->assertSame(AuditLog::REDACTED, $properties['client_ip']);
        $this->assertSame(AuditLog::REDACTED, $properties['mobile']);
        $this->assertSame(AuditLog::REDACTED, $properties['api_key']);
        $this->assertSame(AuditLog::REDACTED, $properties['nested']['backup_contact']);
        $this->assertStringNotContainsString('juan@example.com', $properties['note']);
        $this->assertStringNotContainsString('203.0.113.7', $properties['note']);
        $this->assertStringContainsString('Called from', $properties['note']);

        // …structure and non-PII intact.
        $this->assertSame('Juan Dela Cruz', $properties['resident_name']);
        $this->assertSame('BLT-2026-0001', $properties['case_number']);
        $this->assertSame(42, $properties['resident_id']);
        $this->assertSame('Valid correction request', $properties['reason']);
        $this->assertSame(['status' => 'Pending'], $properties['filters']);

        // Event, actor, timestamp and columns untouched.
        $this->assertSame('system.cache_cleared', $log->event);
        $this->assertSame(7, $log->actor_id);
        $this->assertSame('actor@example.com', $log->actor_email);
        $this->assertSame('subject@example.com', $log->user_email);
        $this->assertSame('203.0.113.9', $log->ip_address);
        $this->assertNotNull($log->occurred_at);
    }

    public function test_prune_still_deletes_rows_older_than_retention(): void
    {
        $old = $this->makeLog('system.cache_cleared', 400, ['note' => 'ancient']);
        $kept = $this->makeLog('system.cache_cleared', 10, $this->piiProperties());

        $this->artisan('audit:prune-logs')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $kept->id]);
        $this->assertSame(AuditLog::REDACTED, $kept->refresh()->properties['contact_email']);
    }

    public function test_dry_run_reports_both_counts_without_changing_anything(): void
    {
        $this->makeLog('system.cache_cleared', 400, ['note' => 'ancient']);
        $log = $this->makeLog('system.cache_cleared', 10, $this->piiProperties());

        $this->artisan('audit:prune-logs', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Would delete 1 audit_logs row(s)')
            ->expectsOutputToContain('Would anonymize 1 retained audit_logs row(s)');

        $this->assertSame(2, AuditLog::count());
        $this->assertSame('juan@example.com', $log->refresh()->properties['contact_email']);
    }

    public function test_skip_anonymize_deletes_without_rewriting_retained_rows(): void
    {
        $this->makeLog('system.cache_cleared', 400, ['note' => 'ancient']);
        $log = $this->makeLog('system.cache_cleared', 10, $this->piiProperties());

        $this->artisan('audit:prune-logs', ['--skip-anonymize' => true])->assertSuccessful();

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('juan@example.com', $log->refresh()->properties['contact_email']);
    }

    public function test_retention_floor_still_refuses_small_windows(): void
    {
        $this->artisan('audit:prune-logs', ['--days' => 7])->assertFailed();
        $this->assertSame(0, AuditLog::count());
    }
}
