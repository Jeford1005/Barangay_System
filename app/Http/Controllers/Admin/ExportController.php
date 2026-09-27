<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\Household;
use App\Models\Resident;
use App\Models\Welfare;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV downloads of the five data sets the barangay re-uses in spreadsheets.
 *
 * Rows are streamed straight to the output buffer in id chunks (no giant
 * in-memory table), a UTF-8 BOM is written first so Excel decodes accented
 * names correctly, and every cell is neutralised against formula injection
 * before it is written. Each download is recorded in the audit trail.
 */
class ExportController extends Controller
{
    /** Belt & braces — the route already whitelists these with whereIn(). */
    private const DATASETS = ['residents', 'households', 'blotter', 'welfare', 'certificates'];

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin() ?? false, 403);

        $dataset = (string) $request->route('dataset');
        abort_unless(in_array($dataset, self::DATASETS, true), 404);

        $filters = $this->appliedFilters($request, $dataset);

        [$query, $header, $row] = $this->build($dataset, $filters);

        AuditLog::record('exported', 'export', null, null, [
            'dataset' => $dataset,
            'filters' => $filters,
        ]);

        $filename = ($dataset === 'certificates' ? 'certificate-issuances' : $dataset)
            .'-'.now()->format('Y-m-d').'.csv';

        return new StreamedResponse(function () use ($query, $header, $row): void {
            $stream = fopen('php://output', 'wb');

            fwrite($stream, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($stream, $header, ',', '"', '\\');

            $query->chunkById(500, function ($rows) use ($stream, $row): void {
                foreach ($rows as $model) {
                    fputcsv(
                        $stream,
                        array_map(fn ($cell): mixed => $this->cell($cell), $row($model)),
                        ',',
                        '"',
                        '\\'
                    );
                }
            });

            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Filters                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Whitelist + normalise the query string. Only filters that make sense
     * for the requested data set are kept; the array that comes back is what
     * is applied to the query *and* what gets written to the audit log.
     *
     * @return array<string, mixed>
     */
    private function appliedFilters(Request $request, string $dataset): array
    {
        $filters = [];

        $search = mb_substr(trim((string) $request->query('search')), 0, 100);
        if ($search !== '') {
            $filters['search'] = $search;
        }

        // Purok only exists on residents, households and blotter records.
        if (in_array($dataset, ['residents', 'households', 'blotter'], true)) {
            $purokId = $this->positiveInt($request->query('purok_id'));
            if ($purokId !== null) {
                $filters['purok_id'] = $purokId;
            }
        }

        if ($dataset === 'welfare') {
            $assistanceType = mb_substr(trim((string) $request->query('assistance_type')), 0, 30);
            if ($assistanceType !== '') {
                $filters['assistance_type'] = $assistanceType;
            }
        }

        if ($dataset === 'certificates') {
            $documentId = $this->positiveInt($request->query('document_id'));
            if ($documentId !== null) {
                $filters['document_id'] = $documentId;
            }
        }

        // Every data set carries a status column.
        $status = mb_substr(trim((string) $request->query('status')), 0, 30);
        if ($status !== '') {
            $filters['status'] = $status;
        }

        return $filters;
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = (string) $value;

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /* ------------------------------------------------------------------ */
    /* Data sets                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Builder, 1: list<string>, 2: Closure(object): array<int, mixed>}
     */
    private function build(string $dataset, array $filters): array
    {
        $like = isset($filters['search'])
            ? '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['search']).'%'
            : null;

        switch ($dataset) {
            case 'residents':
                $query = Resident::query()->with(['purok:id,name', 'household:id,household_number']);

                if ($like !== null) {
                    $query->where(function (Builder $q) use ($like): void {
                        $q->where('full_name', 'like', $like)
                            ->orWhere('address', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                }
                $this->applyCommon($query, $filters, ['purok_id', 'status']);

                return [
                    $query,
                    ['ID', 'Full Name', 'First', 'Middle', 'Last', 'Sex', 'Age', 'Civil Status',
                        'Birth Date', 'Occupation', 'Phone', 'Email', 'Address', 'Purok',
                        'Household', 'Status', 'Photo', 'Created'],
                    fn (Resident $resident): array => [
                        $resident->id,
                        $resident->full_name,
                        $resident->first_name,
                        $resident->middle_name,
                        $resident->last_name,
                        $resident->sex,
                        $resident->age,
                        $resident->civil_status,
                        $resident->birth_date?->format('Y-m-d'),
                        $resident->occupation,
                        $resident->phone,
                        $resident->email,
                        $resident->address,
                        $resident->purok?->name,
                        $resident->household?->household_number,
                        $resident->status,
                        $resident->photo_path,
                        $resident->created_at?->format('Y-m-d H:i:s'),
                    ],
                ];

            case 'households':
                $query = Household::query()->with(['purok:id,name', 'head:id,full_name']);

                if ($like !== null) {
                    $query->where(function (Builder $q) use ($like): void {
                        $q->where('household_number', 'like', $like)
                            ->orWhere('address', 'like', $like);
                    });
                }
                $this->applyCommon($query, $filters, ['purok_id', 'status']);

                return [
                    $query,
                    ['Household No', 'Address', 'Purok', 'Head of Household', 'House Type',
                        'Ownership', 'Status', 'Members', 'Created'],
                    fn (Household $household): array => [
                        $household->household_number,
                        $household->address,
                        $household->purok?->name,
                        $household->head?->full_name,
                        $household->house_type,
                        $household->ownership,
                        $household->status,
                        $household->member_count,
                        $household->created_at?->format('Y-m-d H:i:s'),
                    ],
                ];

            case 'blotter':
                $query = Blotter::query()->with(['purok:id,name', 'recorder:id,name']);

                if ($like !== null) {
                    $query->where(function (Builder $q) use ($like): void {
                        $q->where('case_number', 'like', $like)
                            ->orWhere('incident_type', 'like', $like)
                            ->orWhere('location', 'like', $like)
                            ->orWhere('complainant_name', 'like', $like)
                            ->orWhere('respondent_name', 'like', $like);
                    });
                }
                $this->applyCommon($query, $filters, ['purok_id', 'status']);

                return [
                    $query,
                    ['Case No', 'Incident Date', 'Time', 'Incident Type', 'Location', 'Purok',
                        'Complainant', 'Complainant Contact', 'Respondent', 'Respondent Contact',
                        'Narrative', 'Handling Officer', 'Arrest Made', 'Status',
                        'Resolution Notes', 'Recorded By', 'Created'],
                    fn (Blotter $blotter): array => [
                        $blotter->case_number,
                        $blotter->incident_date?->format('Y-m-d'),
                        $blotter->incident_time?->format('H:i'),
                        $blotter->incident_type,
                        $blotter->location,
                        $blotter->purok?->name,
                        $blotter->complainant_name,
                        $blotter->complainant_contact,
                        $blotter->respondent_name,
                        $blotter->respondent_contact,
                        $blotter->narrative,
                        $blotter->handling_officer,
                        $blotter->arrest_made,
                        $blotter->status,
                        $blotter->resolution_notes,
                        $blotter->recorder?->name,
                        $blotter->created_at?->format('Y-m-d H:i:s'),
                    ],
                ];

            case 'welfare':
                $query = Welfare::query()->with(['resident:id,full_name', 'reviewer:id,name']);

                if ($like !== null) {
                    $query->where(function (Builder $q) use ($like): void {
                        $q->where('assistance_type', 'like', $like)
                            ->orWhereHas('resident', fn (Builder $resident) => $resident
                                ->where('full_name', 'like', $like));
                    });
                }
                $this->applyCommon($query, $filters, ['assistance_type', 'status']);

                return [
                    $query,
                    ['ID', 'Resident', 'Assistance Type', 'Requested Amount', 'Granted Amount',
                        'Status', 'Request Date', 'Notes', 'Reviewed By', 'Created'],
                    fn (Welfare $welfare): array => [
                        $welfare->id,
                        $welfare->resident?->full_name,
                        $welfare->assistance_type,
                        $welfare->requested_amount,
                        $welfare->amount,
                        $welfare->status,
                        $welfare->request_date?->format('Y-m-d'),
                        $welfare->notes,
                        $welfare->reviewer?->name,
                        $welfare->created_at?->format('Y-m-d H:i:s'),
                    ],
                ];

            default: // certificates
                $query = CertificateIssuance::query()->with([
                    'document:id,code,title',
                    'resident:id,full_name',
                    'issuer:id,name',
                ]);

                if ($like !== null) {
                    $query->where(function (Builder $q) use ($like): void {
                        $q->where('control_number', 'like', $like)
                            ->orWhere('purpose', 'like', $like)
                            ->orWhereHas('resident', fn (Builder $resident) => $resident
                                ->where('full_name', 'like', $like));
                    });
                }
                $this->applyCommon($query, $filters, ['document_id', 'status']);

                return [
                    $query,
                    ['Control No', 'Document Code', 'Document Title', 'Resident', 'Purpose', 'Fee',
                        'Copies', 'Status', 'Issued By', 'Issued At', 'Void Reason', 'Created'],
                    fn (CertificateIssuance $issuance): array => [
                        $issuance->control_number,
                        $issuance->document?->code,
                        $issuance->document?->title,
                        $issuance->resident?->full_name ?? $issuance->recipient_snapshot['full_name'] ?? null,
                        $issuance->purpose,
                        $issuance->fee,
                        $issuance->copies,
                        $issuance->status,
                        $issuance->issuer?->name,
                        $issuance->issued_at?->format('Y-m-d H:i:s'),
                        $issuance->void_reason,
                        $issuance->created_at?->format('Y-m-d H:i:s'),
                    ],
                ];
        }
    }

    /**
     * Apply the whitelisted equality filters that the given data set accepts.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $allowed
     */
    private function applyCommon(Builder $query, array $filters, array $allowed): void
    {
        foreach ($allowed as $column) {
            if (array_key_exists($column, $filters)) {
                $query->where($column, $filters[$column]);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* CSV safety                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Make one value safe to hand to fputcsv.
     *
     * Numbers pass through untouched and nulls become an empty cell; strings
     * are stripped of NUL bytes, trimmed of leading whitespace / BOM and, if
     * a spreadsheet could read them as a formula (=, +, -, @) or if the raw
     * value opens with a control byte, prefixed with a literal apostrophe so
     * Excel/LibreOffice treats them as text.
     */
    private function cell(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $raw = (string) $value;
        $cell = str_replace("\0", '', $raw);

        $cell = ltrim($cell, " \t\n\r\v\f");

        if (str_starts_with($cell, "\xEF\xBB\xBF")) {
            $cell = substr($cell, 3);
        }

        $firstByte = $cell === '' ? '' : $cell[0];
        $looksLikeFormula = $firstByte !== '' && in_array($firstByte, ['=', '+', '-', '@'], true);
        $startsControlByte = $raw !== '' && (ord($raw[0]) <= 0x1F || ord($raw[0]) === 0x7F);

        return ($looksLikeFormula || $startsControlByte) ? "'".$cell : $cell;
    }
}
