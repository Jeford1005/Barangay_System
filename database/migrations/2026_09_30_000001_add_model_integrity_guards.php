<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Model-layer integrity guards backing the Eloquent changes:
     *
     * - residents.user_id already has a plain lookup index (see the
     *   2026-09-25 performance-indexes migration) — deliberately NO unique
     *   index. Legacy duplicates are tolerated and reported (not
     *   destroyed) by `residents:check-account-integrity` and
     *   `data:quality-audit`; a hard constraint would break the archival
     *   and approval flows that move accounts between profiles.
     * - residents.email gets a partial unique index (live rows only) where
     *   the driver supports it, mirroring Resident::emailUniqueRule().
     * - certificate_requests gets a partial unique index preventing a
     *   second Pending row for the same resident+document pair, mirroring
     *   CertificateRequest::hasPendingDuplicate().
     * - The four soft-deletable identifier columns are widened so the
     *   `#DEL{id}` tombstone parked by the model boot hooks always fits
     *   (form-level max lengths are unchanged, so human-entered codes
     *   stay short). SQLite needs no widening: it does not enforce
     *   VARCHAR lengths.
     *
     * Every guarded step reports instead of destroying data: when existing
     * rows already violate an index, the index is skipped with a warning
     * and the violation stays visible to the data-quality audit.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            if ($this->hasLiveDuplicateEmails()) {
                Log::warning('Migration skipped residents_email_live_unique: live duplicate residents.email values exist; resolve them (see data:quality-audit) and re-run.');
            } else {
                DB::statement('CREATE UNIQUE INDEX residents_email_live_unique ON residents (email) WHERE deleted_at IS NULL');
            }

            if ($this->hasPendingRequestDuplicates()) {
                Log::warning('Migration skipped cert_requests_pending_unique: duplicate Pending certificate_requests exist; resolve them and re-run.');
            } else {
                DB::statement("CREATE UNIQUE INDEX cert_requests_pending_unique ON certificate_requests (resident_id, document_id) WHERE status = 'Pending'");
            }
        } else {
            Log::info("Migration skipped partial unique indexes: driver '{$driver}' does not support partial indexes. Model-level guards apply instead.");
        }

        $this->widenIdentifierColumns(false);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS residents_email_live_unique');
            DB::statement('DROP INDEX IF EXISTS cert_requests_pending_unique');
        }

        $this->widenIdentifierColumns(true);
    }

    private function hasLiveDuplicateEmails(): bool
    {
        return DB::table('residents')
            ->whereNotNull('email')
            ->whereNull('deleted_at')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function hasPendingRequestDuplicates(): bool
    {
        return DB::table('certificate_requests')
            ->where('status', 'Pending')
            ->select(['resident_id', 'document_id'])
            ->groupBy(['resident_id', 'document_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    /**
     * Widen (or, on rollback, narrow) the identifier columns that host the
     * soft-delete tombstones. New widths match the CODE_MAX constants on
     * the corresponding models.
     */
    private function widenIdentifierColumns(bool $reverse): void
    {
        $driver = DB::getDriverName();

        // [table, column, widened width, original width]
        $columns = [
            ['households', 'household_code', 40, 20],
            ['blotter', 'case_number', 40, 20],
            ['certificate_issuances', 'control_number', 48, 30],
            ['documents', 'code', 16, 8],
        ];

        foreach ($columns as [$table, $column, $wide, $original]) {
            $width = $reverse ? $original : $wide;

            if ($driver === 'mysql') {
                if ($reverse && $this->maxColumnLength($table, $column) > $original) {
                    Log::warning("Rollback skipped narrowing {$table}.{$column}: existing values exceed {$original} characters.");
                    continue;
                }

                DB::statement("ALTER TABLE {$table} MODIFY {$column} VARCHAR({$width}) NOT NULL");
            } elseif ($driver === 'pgsql') {
                if ($reverse && $this->maxColumnLength($table, $column) > $original) {
                    Log::warning("Rollback skipped narrowing {$table}.{$column}: existing values exceed {$original} characters.");
                    continue;
                }

                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE VARCHAR({$width})");
            }
        }
    }

    private function maxColumnLength(string $table, string $column): int
    {
        return (int) DB::table($table)->selectRaw("MAX(LENGTH({$column})) AS len")->value('len');
    }
};
