<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataQualityAuditCommand extends Command
{
    protected $signature = 'data:quality-audit {--format=text : Output format (text or json)} {--json : Shortcut for --format=json}';

    protected $description = 'Audit core records for integrity issues without changing data';

    protected $aliases = ['data:audit', 'data:quality'];

    /**
     * Foreign keys owned by the audited modules. The referenced Purok and
     * Official tables are used only as lookup tables.
     */
    private const FOREIGN_KEYS = [
        ['table' => 'residents', 'column' => 'purok_id', 'target' => 'puroks', 'label' => 'purok'],
        ['table' => 'residents', 'column' => 'household_id', 'target' => 'households', 'label' => 'household'],
        ['table' => 'residents', 'column' => 'user_id', 'target' => 'users', 'label' => 'user'],
        ['table' => 'residents', 'column' => 'created_by', 'target' => 'users', 'label' => 'creator'],
        ['table' => 'residents', 'column' => 'updated_by', 'target' => 'users', 'label' => 'updater'],
        ['table' => 'households', 'column' => 'purok_id', 'target' => 'puroks', 'label' => 'purok'],
        ['table' => 'households', 'column' => 'head_of_household_id', 'target' => 'residents', 'label' => 'head resident'],
        ['table' => 'households', 'column' => 'created_by', 'target' => 'users', 'label' => 'creator'],
        ['table' => 'households', 'column' => 'updated_by', 'target' => 'users', 'label' => 'updater'],
        ['table' => 'blotter', 'column' => 'complainant_id', 'target' => 'residents', 'label' => 'complainant'],
        ['table' => 'blotter', 'column' => 'accused_id', 'target' => 'residents', 'label' => 'accused resident'],
        ['table' => 'blotter', 'column' => 'officer_id', 'target' => 'officials', 'label' => 'officer'],
        ['table' => 'blotter', 'column' => 'created_by', 'target' => 'users', 'label' => 'creator'],
        ['table' => 'blotter', 'column' => 'updated_by', 'target' => 'users', 'label' => 'updater'],
        ['table' => 'welfare', 'column' => 'beneficiary_id', 'target' => 'residents', 'label' => 'beneficiary'],
        ['table' => 'documents', 'column' => 'created_by', 'target' => 'users', 'label' => 'creator'],
        ['table' => 'documents', 'column' => 'updated_by', 'target' => 'users', 'label' => 'updater'],
        ['table' => 'certificate_requests', 'column' => 'resident_id', 'target' => 'residents', 'label' => 'resident'],
        ['table' => 'certificate_requests', 'column' => 'document_id', 'target' => 'documents', 'label' => 'document'],
        ['table' => 'certificate_requests', 'column' => 'issuance_id', 'target' => 'certificate_issuances', 'label' => 'certificate issuance'],
        ['table' => 'certificate_requests', 'column' => 'reviewed_by', 'target' => 'users', 'label' => 'reviewer'],
        ['table' => 'certificate_issuances', 'column' => 'document_id', 'target' => 'documents', 'label' => 'document'],
        ['table' => 'certificate_issuances', 'column' => 'resident_id', 'target' => 'residents', 'label' => 'resident'],
        ['table' => 'certificate_issuances', 'column' => 'issued_by', 'target' => 'users', 'label' => 'issuer'],
        ['table' => 'certificate_issuances', 'column' => 'voided_by', 'target' => 'users', 'label' => 'voider'],
        ['table' => 'certificate_issuances', 'column' => 'created_by', 'target' => 'users', 'label' => 'creator'],
        ['table' => 'users', 'column' => 'reviewed_by', 'target' => 'users', 'label' => 'reviewer'],
        ['table' => 'users', 'column' => 'suspended_by', 'target' => 'users', 'label' => 'suspender'],
        ['table' => 'resident_applications', 'column' => 'user_id', 'target' => 'users', 'label' => 'applicant'],
        ['table' => 'resident_applications', 'column' => 'purok_id', 'target' => 'puroks', 'label' => 'purok'],
        ['table' => 'resident_applications', 'column' => 'household_id', 'target' => 'households', 'label' => 'household'],
        ['table' => 'resident_applications', 'column' => 'reviewed_by', 'target' => 'users', 'label' => 'reviewer'],
        ['table' => 'resident_record_changes', 'column' => 'resident_id', 'target' => 'residents', 'label' => 'resident'],
        ['table' => 'resident_record_changes', 'column' => 'requested_by', 'target' => 'users', 'label' => 'requester'],
        ['table' => 'resident_record_changes', 'column' => 'reviewed_by', 'target' => 'users', 'label' => 'reviewer'],
    ];

    /**
     * Enum-like columns with their allowed values and nullability.
     *
     * NULL is only reported as a violation for NOT NULL columns; nullable
     * columns skip NULL per column semantics instead of false-positive
     * flagging it.
     */
    private const ENUM_FIELDS = [
        ['table' => 'users', 'column' => 'user_type', 'nullable' => false, 'allowed' => ['admin', 'staff', 'official', 'resident']],
        ['table' => 'users', 'column' => 'status', 'nullable' => false, 'allowed' => ['pending', 'approved', 'rejected']],
        ['table' => 'residents', 'column' => 'sex', 'nullable' => false, 'allowed' => ['Male', 'Female', 'Other']],
        ['table' => 'residents', 'column' => 'civil_status', 'nullable' => false, 'allowed' => ['Single', 'Married', 'Divorced', 'Widowed', 'Separated']],
        ['table' => 'residents', 'column' => 'status', 'nullable' => false, 'allowed' => ['Active', 'Archived']],
        ['table' => 'households', 'column' => 'house_type', 'nullable' => false, 'allowed' => ['Single', 'Duplex', 'Apartment', 'Townhouse', 'Other']],
        ['table' => 'households', 'column' => 'ownership', 'nullable' => false, 'allowed' => ['Owned', 'Rented', 'Leased', 'Occupied']],
        ['table' => 'households', 'column' => 'status', 'nullable' => false, 'allowed' => ['Occupied', 'Vacant', 'Under Construction']],
        ['table' => 'blotter', 'column' => 'status', 'nullable' => false, 'allowed' => ['Open', 'Pending', 'Resolved', 'Dismissed']],
        ['table' => 'blotter', 'column' => 'arrest_made', 'nullable' => false, 'allowed' => ['Yes', 'No']],
        ['table' => 'welfare', 'column' => 'assistance_type', 'nullable' => false, 'allowed' => ['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other']],
        ['table' => 'welfare', 'column' => 'status', 'nullable' => false, 'allowed' => ['Requested', 'Under Review', 'Approved', 'Denied', 'Released']],
        ['table' => 'documents', 'column' => 'document_type', 'nullable' => false, 'allowed' => ['Certificate', 'Permit', 'Clearance', 'ID', 'Other']],
        ['table' => 'documents', 'column' => 'status', 'nullable' => false, 'allowed' => ['Active', 'Inactive', 'Draft']],
        ['table' => 'certificate_requests', 'column' => 'status', 'nullable' => false, 'allowed' => ['Pending', 'Approved', 'Rejected', 'Cancelled']],
        ['table' => 'certificate_issuances', 'column' => 'status', 'nullable' => false, 'allowed' => ['Issued', 'Voided']],
        ['table' => 'resident_applications', 'column' => 'status', 'nullable' => false, 'allowed' => ['Pending', 'Approved', 'Rejected', 'Cancelled']],
        ['table' => 'resident_record_changes', 'column' => 'status', 'nullable' => false, 'allowed' => ['Pending', 'Approved', 'Rejected', 'Cancelled']],
    ];

    private const PHONE_FIELDS = [
        ['table' => 'residents', 'column' => 'phone_number'],
        ['table' => 'blotter', 'column' => 'complainant_phone'],
        ['table' => 'blotter', 'column' => 'accused_phone'],
        ['table' => 'welfare', 'column' => 'beneficiary_phone'],
    ];

    private const EMAIL_FIELDS = [
        ['table' => 'users', 'column' => 'email', 'nullable' => false, 'max' => 150],
        ['table' => 'residents', 'column' => 'email', 'nullable' => true, 'max' => 150],
    ];

    private const ZIP_FIELDS = [
        ['table' => 'households', 'column' => 'zip_code'],
    ];

    public function handle(): int
    {
        $this->line('<options=bold>Barangay data quality audit (read-only)</>');
        $this->line('Scope: residents, households, blotter, welfare, certificates, and users.');

        $sections = [
            'Orphaned foreign keys' => $this->findOrphanedForeignKeys(),
            'Duplicate resident user_id links' => $this->findDuplicateResidentAccountLinks(),
            'Resident account/profile mismatches' => $this->findResidentAccountMismatches(),
            'Household head inconsistencies' => $this->findHouseholdHeadIssues(),
            'Invalid enum values' => $this->findInvalidEnumValues(),
            'Invalid phone/email/ZIP values' => $this->findInvalidContactAndLocationValues(),
            'Duplicate household/document codes' => $this->findDuplicateCodes(),
            'Certificate requests linked to inactive residents' => $this->findCertificateRequestsForInactiveResidents(),
        ];

        $findingCount = array_sum(array_map('count', $sections));
        $format = strtolower((string) $this->option('format'));
        if ($this->option('json')) {
            $format = 'json';
        }
        if (! in_array($format, ['text', 'json'], true)) {
            $format = 'text';
        }

        if ($format === 'json') {
            $this->line((string) json_encode([
                'ok' => $findingCount === 0,
                'finding_count' => $findingCount,
                'sections' => $sections,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $findingCount === 0 ? self::SUCCESS : self::FAILURE;
        }

        $findingCount = 0;
        foreach ($sections as $heading => $findings) {
            $this->newLine();
            $this->line("<options=bold>{$heading}</>");

            if ($findings === []) {
                $this->line('  <fg=green>PASS</> No findings.');

                continue;
            }

            $this->line('  <fg=red;options=bold>FAIL</> '.count($findings).' finding(s).');
            foreach ($findings as $finding) {
                $this->line("  - {$finding}");
            }

            $findingCount += count($findings);
        }

        $this->newLine();

        if ($findingCount === 0) {
            $this->info('PASS No data integrity issues found. No data was changed.');

            return self::SUCCESS;
        }

        $this->error("FAIL Data quality audit found {$findingCount} integrity issue(s). No data was changed.");

        return self::FAILURE;
    }

    private function findOrphanedForeignKeys(): array
    {
        $findings = [];

        foreach (self::FOREIGN_KEYS as $foreignKey) {
            if (! Schema::hasTable($foreignKey['table']) || ! Schema::hasTable($foreignKey['target'])) {
                continue;
            }

            // Streamed in chunks so large tables are never loaded into memory
            // at once; only the two inspected columns are selected.
            DB::table($foreignKey['table'])
                ->whereNotNull($foreignKey['column'])
                ->whereNotIn($foreignKey['column'], function ($query) use ($foreignKey): void {
                    $query->select('id')->from($foreignKey['target']);
                })
                ->orderBy('id')
                ->select('id', $foreignKey['column'])
                ->chunk(2000, function ($rows) use (&$findings, $foreignKey): void {
                    foreach ($rows as $row) {
                        $findings[] = sprintf(
                            '%s.%s: %s id=%d references missing %s id=%s',
                            $foreignKey['table'],
                            $foreignKey['column'],
                            rtrim($foreignKey['table'], 's'),
                            $row->id,
                            $foreignKey['label'],
                            self::formatValue($row->{$foreignKey['column']}),
                        );
                    }
                });
        }

        return $findings;
    }

    private function findDuplicateResidentAccountLinks(): array
    {
        $duplicateUserIds = DB::table('residents')
            ->whereNotNull('user_id')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('user_id')
            ->pluck('user_id');

        $findings = [];

        foreach ($duplicateUserIds as $userId) {
            $residentIds = DB::table('residents')
                ->where('user_id', $userId)
                ->orderBy('id')
                ->pluck('id');

            $findings[] = sprintf(
                'residents.user_id: user id=%s is linked by resident ids %s',
                self::formatValue($userId),
                $residentIds->implode(', '),
            );
        }

        return $findings;
    }

    private function findResidentAccountMismatches(): array
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('residents')) {
            return [];
        }

        $findings = [];
        DB::table('users as u')
            ->leftJoin('residents as r', 'r.user_id', '=', 'u.id')
            ->where('u.user_type', 'resident')
            ->where('u.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('r.id')
                    ->orWhere('r.status', '!=', 'Active')
                    ->orWhereNotNull('r.deleted_at');
            })
            ->orderBy('u.id')
            ->select('u.id')
            ->chunk(2000, function ($users) use (&$findings): void {
                foreach ($users as $user) {
                    $findings[] = sprintf(
                        'users: approved resident account id=%d has no active resident profile',
                        $user->id,
                    );
                }
            });

        DB::table('residents as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('u.user_type', '!=', 'resident')
            ->orderBy('r.id')
            ->select('r.id', 'r.user_id')
            ->chunk(2000, function ($residents) use (&$findings): void {
                foreach ($residents as $resident) {
                    $findings[] = sprintf(
                        'residents.user_id: resident id=%d is linked to non-resident account id=%d',
                        $resident->id,
                        $resident->user_id,
                    );
                }
            });

        return $findings;
    }

    private function findHouseholdHeadIssues(): array
    {
        if (! Schema::hasTable('households') || ! Schema::hasTable('residents')) {
            return [];
        }

        $findings = [];
        $householdsByHead = [];

        // Streamed in chunks with point lookups so neither table is ever
        // loaded into memory at once.
        DB::table('households')
            ->orderBy('id')
            ->select('id', 'head_of_household_id')
            ->chunk(2000, function ($households) use (&$findings, &$householdsByHead): void {
                foreach ($households as $household) {
                    if ($household->head_of_household_id === null) {
                        continue;
                    }

                    $headId = (int) $household->head_of_household_id;
                    $householdsByHead[$headId][] = (int) $household->id;
                    $head = DB::table('residents')
                        ->where('id', $headId)
                        ->first(['id', 'household_id', 'is_household_head']);

                    // A missing head is already reported as an orphaned foreign key.
                    if ($head === null) {
                        continue;
                    }

                    $assignedHouseholdId = $head->household_id === null
                        ? null
                        : (int) $head->household_id;

                    if ($assignedHouseholdId !== (int) $household->id) {
                        $findings[] = sprintf(
                            'households.head_of_household_id: household id=%d selects resident id=%d, but that resident household_id=%s',
                            $household->id,
                            $headId,
                            self::formatValue($head->household_id),
                        );
                    }

                    if (! self::databaseBoolean($head->is_household_head)) {
                        $findings[] = sprintf(
                            'residents.is_household_head: household id=%d selects resident id=%d, but the resident is not marked as a household head',
                            $household->id,
                            $headId,
                        );
                    }
                }
            });

        foreach ($householdsByHead as $headId => $householdIds) {
            if (count($householdIds) < 2) {
                continue;
            }

            $findings[] = sprintf(
                'households.head_of_household_id: resident id=%d is selected by multiple households (%s)',
                $headId,
                implode(', ', $householdIds),
            );
        }

        DB::table('residents')
            ->orderBy('id')
            ->select('id', 'household_id', 'is_household_head')
            ->chunk(2000, function ($residents) use (&$findings): void {
                foreach ($residents as $resident) {
                    if (! self::databaseBoolean($resident->is_household_head)) {
                        continue;
                    }

                    $householdId = $resident->household_id === null
                        ? null
                        : (int) $resident->household_id;

                    if ($householdId === null) {
                        $findings[] = sprintf(
                            'residents.is_household_head: resident id=%d is marked as a household head without a household',
                            $resident->id,
                        );

                        continue;
                    }

                    $household = DB::table('households')
                        ->where('id', $householdId)
                        ->first(['id', 'head_of_household_id']);

                    if ($household === null) {
                        $findings[] = sprintf(
                            'residents.is_household_head: resident id=%d is marked as a household head but household id=%d does not exist',
                            $resident->id,
                            $householdId,
                        );

                        continue;
                    }

                    if ((int) $household->head_of_household_id !== (int) $resident->id) {
                        $findings[] = sprintf(
                            'residents.is_household_head: resident id=%d is marked as the head of household id=%d, but the household does not select that resident',
                            $resident->id,
                            $householdId,
                        );
                    }
                }
            });

        return $findings;
    }

    private function findInvalidEnumValues(): array
    {
        $findings = [];

        foreach (self::ENUM_FIELDS as $field) {
            if (! Schema::hasTable($field['table'])) {
                continue;
            }

            // Streamed in chunks with a narrow column select; NULL is skipped
            // for nullable columns per column semantics.
            DB::table($field['table'])
                ->orderBy('id')
                ->select('id', $field['column'])
                ->chunk(2000, function ($rows) use (&$findings, $field): void {
                    foreach ($rows as $row) {
                        $value = $row->{$field['column']};

                        if ($value === null && ($field['nullable'] ?? false)) {
                            continue;
                        }

                        if ($value !== null && in_array($value, $field['allowed'], true)) {
                            continue;
                        }

                        $findings[] = sprintf(
                            '%s.%s: %s id=%d has invalid value %s (allowed: %s)',
                            $field['table'],
                            $field['column'],
                            rtrim($field['table'], 's'),
                            $row->id,
                            self::formatValue($value),
                            implode(', ', $field['allowed']),
                        );
                    }
                });
        }

        return $findings;
    }

    private function findInvalidContactAndLocationValues(): array
    {
        $findings = [];

        foreach (self::PHONE_FIELDS as $field) {
            $this->eachFieldRow($field['table'], $field['column'], function ($rows) use (&$findings, $field): void {
                foreach ($rows as $row) {
                    if ($row->{$field['column']} === null) {
                        continue;
                    }

                    if (! self::isValidPhone($row->{$field['column']})) {
                        $findings[] = sprintf(
                            '%s.%s: %s id=%d has invalid phone value %s',
                            $field['table'],
                            $field['column'],
                            rtrim($field['table'], 's'),
                            $row->id,
                            self::formatValue($row->{$field['column']}),
                        );
                    }
                }
            });
        }

        foreach (self::EMAIL_FIELDS as $field) {
            $this->eachFieldRow($field['table'], $field['column'], function ($rows) use (&$findings, $field): void {
                foreach ($rows as $row) {
                    $value = $row->{$field['column']};

                    if ($field['nullable'] && $value === null) {
                        continue;
                    }

                    if (! is_string($value) || mb_strlen($value) > $field['max'] || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                        $findings[] = sprintf(
                            '%s.%s: %s id=%d has invalid email value %s',
                            $field['table'],
                            $field['column'],
                            rtrim($field['table'], 's'),
                            $row->id,
                            self::formatValue($value),
                        );
                    }
                }
            });
        }

        foreach (self::ZIP_FIELDS as $field) {
            $this->eachFieldRow($field['table'], $field['column'], function ($rows) use (&$findings, $field): void {
                foreach ($rows as $row) {
                    $value = $row->{$field['column']};

                    if ($value === null) {
                        continue;
                    }

                    if (! is_string($value) || preg_match('/^\d{4,10}$/D', $value) !== 1) {
                        $findings[] = sprintf(
                            '%s.%s: household id=%d has invalid ZIP value %s',
                            $field['table'],
                            $field['column'],
                            $row->id,
                            self::formatValue($value),
                        );
                    }
                }
            });
        }

        return $findings;
    }

    private function findDuplicateCodes(): array
    {
        return [
            ...$this->findDuplicateCodeGroups('households', 'household_code'),
            ...$this->findDuplicateCodeGroups('documents', 'code'),
        ];
    }

    private function findDuplicateCodeGroups(string $table, string $column): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $findings = [];

        // Only duplicated non-NULL values are reported: NULL means "no code",
        // so grouping NULLs together would be a false positive.
        DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->orderBy($column)
            ->chunk(500, function ($rows) use ($table, $column, &$findings): void {
                foreach ($rows as $row) {
                    $value = $row->{$column};
                    $ids = DB::table($table)
                        ->where($column, $value)
                        ->orderBy('id')
                        ->pluck('id');

                    $findings[] = sprintf(
                        '%s.%s: code %s is used by %s ids %s',
                        $table,
                        $column,
                        self::formatValue($value),
                        rtrim($table, 's'),
                        implode(', ', $ids->all()),
                    );
                }
            });

        return $findings;
    }

    private function findCertificateRequestsForInactiveResidents(): array
    {
        if (! Schema::hasTable('certificate_requests') || ! Schema::hasTable('residents')) {
            return [];
        }

        $findings = [];

        DB::table('certificate_requests')
            ->join('residents', 'residents.id', '=', 'certificate_requests.resident_id')
            ->where(function ($query): void {
                $query->whereNull('residents.status')
                    ->orWhere('residents.status', '!=', 'Active')
                    ->orWhereNotNull('residents.deleted_at');
            })
            ->orderBy('certificate_requests.id')
            ->select([
                'certificate_requests.id as request_id',
                'certificate_requests.resident_id',
                'residents.status',
                'residents.deleted_at',
            ])
            ->chunk(2000, function ($rows) use (&$findings): void {
                foreach ($rows as $row) {
                    $reasons = [];

                    if ($row->status !== 'Active') {
                        $reasons[] = 'status is '.self::formatValue($row->status);
                    }

                    if ($row->deleted_at !== null) {
                        $reasons[] = 'resident is soft-deleted';
                    }

                    $findings[] = sprintf(
                        'certificate_requests.resident_id: request id=%d links resident id=%d, but %s',
                        $row->request_id,
                        $row->resident_id,
                        implode(' and ', $reasons),
                    );
                }
            });

        return $findings;
    }

    /**
     * Stream a table's id + column pairs in chunks so large tables are never
     * loaded into memory at once.
     */
    private function eachFieldRow(string $table, string $column, callable $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->orderBy('id')
            ->select('id', $column)
            ->chunk(2000, $callback);
    }

    private static function isValidPhone(mixed $value): bool
    {
        if (! is_string($value) || mb_strlen($value) > 15) {
            return false;
        }

        return preg_match('/\A(?=.*\d)\+?[0-9()\-\s]+\z/', $value) === 1;
    }

    private static function databaseBoolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private static function formatValue(mixed $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return htmlspecialchars(
            $encoded === false ? gettype($value) : $encoded,
            ENT_NOQUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }
}
