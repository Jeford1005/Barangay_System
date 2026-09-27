<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\Household;
use App\Models\Resident;
use App\Models\Welfare;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams filtered administrative records as UTF-8 CSV files.
 *
 * The service deliberately owns both the query and the row projection. This
 * keeps exports on the same records and filters as the module indexes while
 * allowing the response to be consumed in bounded chunks.
 */
class ExportService
{
    /** Maximum number of characters used in a free-text export search. */
    public const MAX_SEARCH_LENGTH = 100;

    /** Number of records held in memory at a time while streaming. */
    public const CHUNK_SIZE = 500;

    /** @var array<string, string> */
    private const DATASET_FILENAMES = [
        'residents' => 'residents',
        'households' => 'households',
        'blotter' => 'blotter',
        'welfare' => 'welfare',
        'certificates' => 'certificate-issuances',
    ];

    /** @var array<string, list<string>> */
    private const DATASET_HEADERS = [
        'residents' => [
            'ID',
            'First Name',
            'Middle Name',
            'Last Name',
            'Suffix',
            'Full Name',
            'Birth Date',
            'Birthplace',
            'Sex',
            'Civil Status',
            'Nationality',
            'Religion',
            'Education Level',
            'Occupation',
            'Spouse Name',
            'Blood Type',
            'Phone Number',
            'Email',
            'Address',
            'Residency Status',
            'Voter Status',
            'Household Head',
            'Purok',
            'Household Code',
            'Status',
            'Created At',
            'Updated At',
        ],
        'households' => [
            'ID',
            'Household Code',
            'Purok',
            'Sitio',
            'Street',
            'Barangay',
            'Municipality',
            'Province',
            'Region',
            'ZIP Code',
            'House Type',
            'Lot Area',
            'Floor Area',
            'Year Built',
            'Ownership',
            'Number of Members',
            'Head of Household',
            'Head Resident ID',
            'Status',
            'Remarks',
            'Created At',
            'Updated At',
        ],
        'blotter' => [
            'ID',
            'Case Number',
            'Complainant ID',
            'Complainant Name',
            'Complainant Address',
            'Complainant Phone',
            'Accused ID',
            'Accused Name',
            'Accused Address',
            'Accused Phone',
            'Complaint Type',
            'Complaint Subtype',
            'Complaint Date',
            'Complaint Time',
            'Alleged Offense',
            'Status',
            'Disposition',
            'Disposition Date',
            'Arrest Made',
            'Investigator',
            'Officer ID',
            'Officer Name',
            'Remarks',
            'Created At',
            'Updated At',
        ],
        'welfare' => [
            'ID',
            'Beneficiary ID',
            'Beneficiary Name',
            'Beneficiary Address',
            'Beneficiary Phone',
            'Assistance Type',
            'Program Name',
            'Program Description',
            'Requested Amount',
            'Approved Amount',
            'Status',
            'Request Date',
            'Approval Date',
            'Release Date',
            'Remarks',
            'Created At',
            'Updated At',
        ],
        'certificates' => [
            'ID',
            'Control Number',
            'Document ID',
            'Certificate Code',
            'Certificate Type',
            'Resident ID',
            'Resident Name',
            'Purok',
            'Purpose',
            'Copies',
            'Fee',
            'Status',
            'Remarks',
            'Issued By',
            'Issued At',
            'Voided By',
            'Voided At',
            'Created At',
            'Updated At',
        ],
    ];

    public function supports(string $dataset): bool
    {
        $dataset = strtolower(trim($dataset));

        return isset(self::DATASET_FILENAMES[$dataset]);
    }

    /**
     * Build the same effective query used by an export request.
     *
     * This is public so callers and tests can inspect filter parity without
     * consuming a streamed response. It never changes a source record.
     */
    public function query(string $dataset, Request $request): Builder
    {
        $dataset = strtolower(trim($dataset));

        return match ($dataset) {
            'residents' => $this->residentsQuery($request),
            'households' => $this->householdsQuery($request),
            'blotter' => $this->blotterQuery($request),
            'welfare' => $this->welfareQuery($request),
            'certificates' => $this->certificatesQuery($request),
            default => throw new InvalidArgumentException("Unsupported export dataset [{$dataset}]."),
        };
    }

    /**
     * Stream a filtered dataset as a downloadable CSV response.
     */
    public function stream(string $dataset, Request $request): StreamedResponse
    {
        $dataset = strtolower(trim($dataset));

        if (! $this->supports($dataset)) {
            throw new InvalidArgumentException("Unsupported export dataset [{$dataset}].");
        }

        $query = $this->query($dataset, $request);
        $headers = self::DATASET_HEADERS[$dataset];
        $actor = $request->user();

        AuditLog::record(
            "export.{$dataset}",
            $actor?->id,
            $actor?->email,
            $request->ip(),
            $request->userAgent(),
            [
                'format' => 'csv',
                'type' => $dataset,
                'resource' => $dataset,
                'filters' => $this->filtersForAudit($dataset, $request),
            ],
        );

        $filename = sprintf(
            '%s-%s.csv',
            self::DATASET_FILENAMES[$dataset],
            now()->format('Y-m-d'),
        );

        return response()->stream(function () use ($query, $dataset, $headers): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the CSV output stream.');
            }

            try {
                // Excel and other spreadsheet readers use the BOM to detect UTF-8.
                $this->write($handle, "\xEF\xBB\xBF");
                $this->writeRow($handle, $headers);

                $query->chunkById(self::CHUNK_SIZE, function ($records) use ($handle, $dataset): void {
                    foreach ($records as $record) {
                        $this->writeRow($handle, $this->row($dataset, $record));
                    }
                });

                fflush($handle);
            } finally {
                fclose($handle);
            }
        }, 200, $this->responseHeaders($filename));
    }

    /**
     * Alias that reads naturally for callers that want a download response.
     */
    public function download(string $dataset, Request $request): StreamedResponse
    {
        return $this->stream($dataset, $request);
    }

    /**
     * Short generic entry point for integrations that do not need to know
     * whether the response is being streamed or downloaded.
     */
    public function export(string $dataset, Request $request): StreamedResponse
    {
        return $this->stream($dataset, $request);
    }

    /**
     * Return the column labels for a supported dataset.
     *
     * @return list<string>
     */
    public function headers(string $dataset): array
    {
        $dataset = strtolower(trim($dataset));

        if (! $this->supports($dataset)) {
            throw new InvalidArgumentException("Unsupported export dataset [{$dataset}].");
        }

        return self::DATASET_HEADERS[$dataset];
    }

    /**
     * Normalize a search term before it reaches a LIKE predicate.
     *
     * The raw value is capped before tag stripping so a very large query
     * parameter cannot make the search itself unnecessarily expensive.
     */
    public static function capSearch(mixed $value): ?string
    {
        if ($value === null || (! is_scalar($value) && ! $value instanceof Stringable)) {
            return null;
        }

        $search = mb_substr((string) $value, 0, self::MAX_SEARCH_LENGTH);
        $search = strip_tags($search);

        return $search === '' ? null : $search;
    }

    /**
     * Prevent a CSV cell from being interpreted as a spreadsheet formula.
     *
     * Native numeric values remain numeric; text beginning with a formula
     * sigil (including after leading whitespace/control characters) receives a
     * leading apostrophe, which spreadsheet applications treat as literal
     * text.
     */
    public static function neutralizeSpreadsheetFormula(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $nativeNumeric = is_int($value) || is_float($value);

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (! is_scalar($value) && ! $value instanceof Stringable) {
            $value = '';
        }

        $value = str_replace("\0", '', (string) $value);

        if ($value === '' || $nativeNumeric) {
            return $value;
        }

        $withoutWhitespace = ltrim($value, " \t\r\n");
        $withoutBom = preg_replace('/^\xEF\xBB\xBF/', '', $withoutWhitespace) ?: $withoutWhitespace;
        $firstCharacter = $withoutBom === '' ? '' : $withoutBom[0];
        $startsWithControlCharacter = $value !== '' && (ord($value[0]) <= 0x1F || ord($value[0]) === 0x7F);

        if ($startsWithControlCharacter || in_array($firstCharacter, ['=', '+', '-', '@'], true)) {
            return "'{$value}";
        }

        return $value;
    }

    private function residentsQuery(Request $request): Builder
    {
        $query = Resident::query()->with(['purok', 'household']);
        $search = $this->search($request);

        if ($search !== null) {
            $fullNameSql = $query->getModel()->getConnection()->getDriverName() === 'sqlite'
                ? "first_name || ' ' || last_name"
                : "CONCAT(first_name, ' ', last_name)";

            $query->where(function (Builder $query) use ($search, $fullNameSql): void {
                $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw($fullNameSql.' LIKE ?', ['%'.$search.'%']);
            });
        }

        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        if ($request->filled('household_id')) {
            $query->where('household_id', $request->integer('household_id'));
        }

        $status = $this->inputString($request, 'status');
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    private function householdsQuery(Request $request): Builder
    {
        $query = Household::query()->with(['purok', 'head']);
        $search = $this->search($request);

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('household_code', 'like', '%'.$search.'%')
                    ->orWhere('street', 'like', '%'.$search.'%')
                    ->orWhere('barangay', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        return $query;
    }

    private function blotterQuery(Request $request): Builder
    {
        $query = Blotter::query()->with('officer');
        $search = $this->search($request);

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('case_number', 'like', '%'.$search.'%')
                    ->orWhere('complainant_name', 'like', '%'.$search.'%')
                    ->orWhere('accused_name', 'like', '%'.$search.'%')
                    ->orWhere('complaint_type', 'like', '%'.$search.'%');
            });
        }

        $status = $this->inputString($request, 'status');
        if ($status !== null && in_array($status, ['Open', 'Pending', 'Resolved', 'Dismissed'], true)) {
            $query->where('status', $status);
        }

        return $query;
    }

    private function welfareQuery(Request $request): Builder
    {
        $query = Welfare::query();
        $search = $this->search($request);

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('beneficiary_name', 'like', '%'.$search.'%')
                    ->orWhere('program_name', 'like', '%'.$search.'%');
            });
        }

        $status = $this->inputString($request, 'status');
        if ($status !== null && in_array($status, ['Requested', 'Under Review', 'Approved', 'Denied', 'Released'], true)) {
            $query->where('status', $status);
        }

        $assistanceType = $this->inputString($request, 'assistance_type');
        if ($assistanceType !== null && in_array($assistanceType, ['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other'], true)) {
            $query->where('assistance_type', $assistanceType);
        }

        return $query;
    }

    private function certificatesQuery(Request $request): Builder
    {
        $query = CertificateIssuance::query()
            ->with(['document', 'resident.purok', 'issuer']);
        $search = $this->search($request);

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('control_number', 'like', '%'.$search.'%')
                    ->orWhere('purpose', 'like', '%'.$search.'%')
                    ->orWhereHas('resident', function (Builder $residentQuery) use ($search): void {
                        $residentQuery->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = $this->inputString($request, 'status');
        if ($status !== null && in_array($status, ['Issued', 'Voided'], true)) {
            $query->where('status', $status);
        }

        if ($request->filled('document_id')) {
            $query->where('document_id', $request->integer('document_id'));
        }

        return $query;
    }

    private function search(Request $request): ?string
    {
        if (! $request->filled('search')) {
            return null;
        }

        return static::capSearch($request->input('search'));
    }

    private function inputString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        if ($value === null || (! is_scalar($value) && ! $value instanceof Stringable)) {
            return null;
        }

        return (string) $value;
    }

    /** @return array<string, int|string> */
    private function filtersForAudit(string $dataset, Request $request): array
    {
        $filters = [];
        $search = $this->search($request);

        if ($search !== null) {
            $filters['search'] = $search;
        }

        $integerFilters = match ($dataset) {
            'residents' => ['purok_id', 'household_id'],
            'households' => ['purok_id'],
            'certificates' => ['document_id'],
            default => [],
        };

        foreach ($integerFilters as $filter) {
            if ($request->filled($filter)) {
                $filters[$filter] = $request->integer($filter);
            }
        }

        $textFilters = match ($dataset) {
            'residents' => ['status'],
            'blotter' => ['status'],
            'welfare' => ['status', 'assistance_type'],
            'certificates' => ['status'],
            default => [],
        };

        foreach ($textFilters as $filter) {
            if (! $request->filled($filter)) {
                continue;
            }

            $value = $this->inputString($request, $filter);
            if ($value === null) {
                continue;
            }

            $allowed = match ($filter) {
                'status' => match ($dataset) {
                    'residents' => null,
                    'blotter' => ['Open', 'Pending', 'Resolved', 'Dismissed'],
                    'welfare' => ['Requested', 'Under Review', 'Approved', 'Denied', 'Released'],
                    'certificates' => ['Issued', 'Voided'],
                    default => null,
                },
                'assistance_type' => ['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other'],
                default => null,
            };

            if ($allowed === null || in_array($value, $allowed, true)) {
                $filters[$filter] = $value;
            }
        }

        return $filters;
    }

    /** @return array<int|string, mixed> */
    private function row(string $dataset, object $record): array
    {
        return match ($dataset) {
            'residents' => $this->residentRow($record),
            'households' => $this->householdRow($record),
            'blotter' => $this->blotterRow($record),
            'welfare' => $this->welfareRow($record),
            'certificates' => $this->certificateRow($record),
            default => throw new InvalidArgumentException("Unsupported export dataset [{$dataset}]."),
        };
    }

    /** @return list<mixed> */
    private function residentRow(Resident $resident): array
    {
        return [
            $resident->id,
            $resident->first_name,
            $resident->middle_name,
            $resident->last_name,
            $resident->suffix,
            $resident->full_name,
            $this->date($resident->birth_date),
            $resident->birthplace,
            $resident->sex,
            $resident->civil_status,
            $resident->nationality,
            $resident->religion,
            $resident->education_level,
            $resident->occupation,
            $resident->spouse_name,
            $resident->blood_type,
            $resident->phone_number,
            $resident->email,
            $resident->address,
            $resident->residency_status,
            $this->yesNo($resident->voter_status),
            $this->yesNo($resident->is_household_head),
            $resident->purok?->name,
            $resident->household?->household_code,
            $resident->status,
            $this->dateTime($resident->created_at),
            $this->dateTime($resident->updated_at),
        ];
    }

    /** @return list<mixed> */
    private function householdRow(Household $household): array
    {
        return [
            $household->id,
            $household->household_code,
            $household->purok?->name,
            $household->sitio,
            $household->street,
            $household->barangay,
            $household->municipality,
            $household->province,
            $household->region,
            $household->zip_code,
            $household->house_type,
            $household->lot_area,
            $household->floor_area,
            $household->year_built,
            $household->ownership,
            $household->num_members,
            $household->head?->full_name,
            $household->head_of_household_id,
            $household->status,
            $household->remarks,
            $this->dateTime($household->created_at),
            $this->dateTime($household->updated_at),
        ];
    }

    /** @return list<mixed> */
    private function blotterRow(Blotter $blotter): array
    {
        return [
            $blotter->id,
            $blotter->case_number,
            $blotter->complainant_id,
            $blotter->complainant_name,
            $blotter->complainant_address,
            $blotter->complainant_phone,
            $blotter->accused_id,
            $blotter->accused_name,
            $blotter->accused_address,
            $blotter->accused_phone,
            $blotter->complaint_type,
            $blotter->complaint_subtype,
            $this->date($blotter->complaint_date),
            $this->date($blotter->complaint_time, 'H:i'),
            $blotter->alleged_offense,
            $blotter->status,
            $blotter->disposition,
            $this->date($blotter->disposition_date),
            $blotter->arrest_made,
            $blotter->investigator,
            $blotter->officer_id,
            $blotter->officer?->full_name,
            $blotter->remarks,
            $this->dateTime($blotter->created_at),
            $this->dateTime($blotter->updated_at),
        ];
    }

    /** @return list<mixed> */
    private function welfareRow(Welfare $welfare): array
    {
        return [
            $welfare->id,
            $welfare->beneficiary_id,
            $welfare->beneficiary_name,
            $welfare->beneficiary_address,
            $welfare->beneficiary_phone,
            $welfare->assistance_type,
            $welfare->program_name,
            $welfare->program_description,
            $this->money($welfare->requested_amount),
            $this->money($welfare->approved_amount),
            $welfare->status,
            $this->date($welfare->request_date),
            $this->date($welfare->approval_date),
            $this->date($welfare->release_date),
            $welfare->remarks,
            $this->dateTime($welfare->created_at),
            $this->dateTime($welfare->updated_at),
        ];
    }

    /** @return list<mixed> */
    private function certificateRow(CertificateIssuance $issuance): array
    {
        $issuer = $issuance->issuer;

        return [
            $issuance->id,
            $issuance->control_number,
            $issuance->document_id,
            $issuance->document?->code,
            $issuance->document?->title,
            $issuance->resident_id,
            $issuance->recipient_snapshot['full_name'] ?? $issuance->resident?->full_name,
            $issuance->recipient_snapshot['purok_name'] ?? $issuance->resident?->purok?->name,
            $issuance->purpose,
            $issuance->copies,
            $this->money($issuance->fee),
            $issuance->status,
            $issuance->remarks,
            $issuer?->name ?: ($issuer?->email ?: $issuance->issued_by),
            $this->dateTime($issuance->created_at),
            $issuance->voided_by,
            $this->dateTime($issuance->voided_at),
            $this->dateTime($issuance->created_at),
            $this->dateTime($issuance->updated_at),
        ];
    }

    private function date(mixed $value, string $format = 'Y-m-d'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $value instanceof DateTimeInterface ? $value->format($format) : (string) $value;
    }

    private function dateTime(mixed $value): string
    {
        return $this->date($value, 'Y-m-d H:i:s');
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function yesNo(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return $value ? 'Yes' : 'No';
    }

    /** @param resource $handle */
    private function write($handle, string $value): void
    {
        if (fwrite($handle, $value) === false) {
            throw new RuntimeException('Unable to write to the CSV output stream.');
        }
    }

    /** @param resource $handle @param list<string> $row */
    private function writeRow($handle, array $row): void
    {
        $safeRow = array_map(fn (mixed $value): string => $this->csvValue($value), $row);

        if (fputcsv($handle, $safeRow, ',', '"', '\\', "\n") === false) {
            throw new RuntimeException('Unable to write a CSV row.');
        }
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $this->dateTime($value);
        }

        return static::neutralizeSpreadsheetFormula($value);
    }

    /** @return array<string, string> */
    private function responseHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Accel-Buffering' => 'no',
        ];
    }
}
