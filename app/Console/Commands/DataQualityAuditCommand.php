<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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

    private const ENUM_FIELDS = [
        ['table' => 'users', 'column' => 'user_type', 'allowed' => ['admin', 'staff', 'official', 'resident']],
        ['table' => 'users', 'column' => 'status', 'allowed' => ['pending', 'approved', 'rejected']],
        ['table' => 'residents', 'column' => 'sex', 'allowed' => ['Male', 'Female', 'Other']],
        ['table' => 'residents', 'column' => 'civil_status', 'allowed' => ['Single', 'Married', 'Divorced', 'Widowed', 'Separated']],
        ['table' => 'residents', 'column' => 'status', 'allowed' => ['Active', 'Archived']],
        ['table' => 'households', 'column' => 'house_type', 'allowed' => ['Single', 'Duplex', 'Apartment', 'Townhouse', 'Other']],
        ['table' => 'households', 'column' => 'ownership', 'allowed' => ['Owned', 'Rented', 'Leased', 'Occupied']],
        ['table' => 'households', 'column' => 'status', 'allowed' => ['Occupied', 'Vacant', 'Under Construction']],
        ['table' => 'blotter', 'column' => 'status', 'allowed' => ['Open', 'Pending', 'Resolved', 'Dismissed']],
        ['table' => 'blotter', 'column' => 'arrest_made', 'allowed' => ['Yes', 'No']],
        ['table' => 'welfare', 'column' => 'assistance_type', 'allowed' => ['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other']],
        ['table' => 'welfare', 'column' => 'status', 'allowed' => ['Requested', 'Under Review', 'Approved', 'Denied', 'Released']],
        ['table' => 'documents', 'column' => 'document_type', 'allowed' => ['Certificate', 'Permit', 'Clearance', 'ID', 'Other']],
        ['table' => 'documents', 'column' => 'status', 'allowed' => ['Active', 'Inactive', 'Draft']],
        ['table' => 'certificate_requests', 'column' => 'status', 'allowed' => ['Pending', 'Approved', 'Rejected', 'Cancelled']],
        ['table' => 'certificate_issuances', 'column' => 'status', 'allowed' => ['Issued', 'Voided']],
        ['table' => 'resident_applications', 'column' => 'status', 'allowed' => ['Pending', 'Approved', 'Rejected', 'Cancelled']],
        ['table' => 'resident_record_changes', 'column' => 'status', 'allowed' => ['Pending', 'Approved', 'Rejected', 'Cancelled']],
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

            $rows = DB::table($foreignKey['table'])
                ->whereNotNull($foreignKey['column'])
                ->whereNotIn($foreignKey['column'], function ($query) use ($foreignKey): void {
                    $query->select('id')->from($foreignKey['target']);
                })
                ->orderBy('id')
                ->get(['id', $foreignKey['column']]);

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
        $approvedWithoutActiveProfile = DB::table('users as u')
            ->leftJoin('residents as r', 'r.user_id', '=', 'u.id')
            ->where('u.user_type', 'resident')
            ->where('u.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('r.id')
                    ->orWhere('r.status', '!=', 'Active')
                    ->orWhereNotNull('r.deleted_at');
            })
            ->orderBy('u.id')
            ->get(['u.id', 'u.email']);

        foreach ($approvedWithoutActiveProfile as $user) {
            $findings[] = sprintf(
                'users: approved resident account id=%d has no active resident profile',
                $user->id,
            );
        }

        $nonResidentLinks = DB::table('residents as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('u.user_type', '!=', 'resident')
            ->orderBy('r.id')
            ->get(['r.id', 'r.user_id', 'u.user_type']);

        foreach ($nonResidentLinks as $resident) {
            $findings[] = sprintf(
                'residents.user_id: resident id=%d is linked to non-resident account id=%d',
                $resident->id,
                $resident->user_id,
            );
        }

        return $findings;
    }

    private function findHouseholdHeadIssues(): array
    {
        $households = DB::table('households')
            ->orderBy('id')
            ->get(['id', 'head_of_household_id']);
        $residentsById = DB::table('residents')
            ->orderBy('id')
            ->get(['id', 'household_id', 'is_household_head'])
            ->keyBy('id');
        $householdsById = $households->keyBy('id');
        $findings = [];
        $householdsByHead = [];

        foreach ($households as $household) {
            if ($household->head_of_household_id === null) {
                continue;
            }

            $headId = (int) $household->head_of_household_id;
            $householdsByHead[$headId][] = (int) $household->id;
            $head = $residentsById->get($headId);

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

        foreach ($residentsById as $resident) {
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

            $household = $householdsById->get($householdId);

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

        return $findings;
    }

    private function findInvalidEnumValues(): array
    {
        $findings = [];

        foreach (self::ENUM_FIELDS as $field) {
            if (! Schema::hasTable($field['table'])) {
                continue;
            }

            $rows = DB::table($field['table'])
                ->orderBy('id')
                ->get(['id', $field['column']]);

            foreach ($rows as $row) {
                $value = $row->{$field['column']};

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
        }

        return $findings;
    }

    private function findInvalidContactAndLocationValues(): array
    {
        $findings = [];

        foreach (self::PHONE_FIELDS as $field) {
            foreach ($this->fieldRows($field['table'], $field['column']) as $row) {
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
        }

        foreach (self::EMAIL_FIELDS as $field) {
            foreach ($this->fieldRows($field['table'], $field['column']) as $row) {
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
        }

        foreach (self::ZIP_FIELDS as $field) {
            foreach ($this->fieldRows($field['table'], $field['column']) as $row) {
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
        $rows = DB::table($table)
            ->orderBy('id')
            ->get(['id', $column]);
        $groups = [];

        foreach ($rows as $row) {
            $value = $row->{$column};
            $key = $value === null ? "\0null" : (string) $value;
            $groups[$key][] = (int) $row->id;
        }

        $findings = [];

        foreach ($groups as $value => $ids) {
            if (count($ids) < 2) {
                continue;
            }

            $findings[] = sprintf(
                '%s.%s: code %s is used by %s ids %s',
                $table,
                $column,
                $value === "\0null" ? self::formatValue(null) : self::formatValue($value),
                rtrim($table, 's'),
                implode(', ', $ids),
            );
        }

        return $findings;
    }

    private function findCertificateRequestsForInactiveResidents(): array
    {
        $rows = DB::table('certificate_requests')
            ->join('residents', 'residents.id', '=', 'certificate_requests.resident_id')
            ->where(function ($query): void {
                $query->whereNull('residents.status')
                    ->orWhere('residents.status', '!=', 'Active')
                    ->orWhereNotNull('residents.deleted_at');
            })
            ->orderBy('certificate_requests.id')
            ->get([
                'certificate_requests.id as request_id',
                'certificate_requests.resident_id',
                'residents.status',
                'residents.deleted_at',
            ]);
        $findings = [];

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

        return $findings;
    }

    private function fieldRows(string $table, string $column): Collection
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        return DB::table($table)
            ->orderBy('id')
            ->get(['id', $column]);
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
