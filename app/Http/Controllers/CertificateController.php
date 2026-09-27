<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certificates\IssueCertificateRequest;
use App\Http\Requests\Certificates\VoidCertificateRequest;
use App\Models\AuditLog;
use App\Models\CertificateIssuance;
use App\Models\Document;
use App\Models\Resident;
use App\Models\SequenceCounter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Barangay office certificate counter: issue, list, print and void.
 *
 * The printable page is intentionally a standalone screen — print() hands it
 * nothing but the issuance and lets the view resolve the Punong Barangay.
 */
class CertificateController extends Controller
{
    /** Issued / voided register with search, filters and today's counter. */
    public function index(Request $request): View
    {
        $query = CertificateIssuance::query()
            ->with(['document', 'resident', 'issuer'])
            ->leftJoin('residents', 'residents.id', '=', 'certificate_issuances.resident_id')
            ->select('certificate_issuances.*');

        $term = trim((string) $request->query('q'));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('certificate_issuances.control_number', 'like', $like)
                    ->orWhere('certificate_issuances.purpose', 'like', $like)
                    ->orWhere('residents.full_name', 'like', $like);
            });
        }

        $status = (string) $request->query('status');

        if (in_array($status, [CertificateIssuance::STATUS_ISSUED, CertificateIssuance::STATUS_VOIDED], true)) {
            $query->where('certificate_issuances.status', $status);
        }

        $documentId = (int) $request->query('document_id');

        if ($documentId > 0) {
            $query->where('certificate_issuances.document_id', $documentId);
        }

        return view('certificates.index', [
            'rows' => $query->orderByDesc('certificate_issuances.id')->paginate(20)->withQueryString(),
            'documents' => Document::query()->orderBy('code')->get(['id', 'code', 'title']),
            'todayCount' => CertificateIssuance::whereDate('created_at', today())->count(),
            'filters' => [
                'q' => $term,
                'status' => in_array($status, [CertificateIssuance::STATUS_ISSUED, CertificateIssuance::STATUS_VOIDED], true) ? $status : '',
                'document_id' => $documentId,
            ],
        ]);
    }

    /** The issuance form: active residents, active certificates/clearances. */
    public function create(Request $request): View
    {
        $residents = Resident::query()
            ->active()
            ->with('purok')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Resident $resident): array => [
                'id' => $resident->id,
                'label' => $resident->full_name.' — '.($resident->resolvedAddress() !== ''
                    ? $resident->resolvedAddress()
                    : ($resident->purok?->name ?? 'No address on file')),
            ]);

        return view('certificates.create', [
            'residents' => $residents,
            'documents' => Document::query()
                ->active()
                ->whereIn('document_type', ['Certificate', 'Clearance'])
                ->orderBy('code')
                ->get(),
            'selectedResident' => old('resident_id', $request->query('resident')),
        ]);
    }

    /** Issue a certificate and jump straight to the printable page. */
    public function store(IssueCertificateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        abort_unless($user !== null, 403);

        $document = Document::find((int) $data['document_id']);
        $resident = Resident::find((int) $data['resident_id']);

        // Defensive re-checks: validation already guaranteed both exist and
        // are usable, but nothing here may proceed without them.
        if ($document === null || $resident === null) {
            return redirect()->route('certificates.create')
                ->with('error', 'The certificate could not be issued — please try again.');
        }

        // Staff always pay the catalog fee; only admins may override it.
        $feeOverride = $data['fee'] ?? null;

        $fee = ($user->isAdmin() && $feeOverride !== null)
            ? round((float) $feeOverride, 2)
            : (float) $document->fee;

        $issuance = DB::transaction(function () use ($document, $resident, $data, $fee, $user): CertificateIssuance {
            $control = SequenceCounter::nextControlNumber($document->code);

            $issuance = CertificateIssuance::create([
                'control_number' => $control,
                'document_id' => $document->id,
                'resident_id' => $resident->id,
                'purpose' => $data['purpose'],
                'fee' => $fee,
                'copies' => (int) $data['copies'],
                'recipient_snapshot' => CertificateIssuance::snapshotFor($resident),
                'status' => CertificateIssuance::STATUS_ISSUED,
                'issued_by' => $user->id,
                'issued_at' => now(),
            ]);

            AuditLog::record('issued', 'certificate_issuance', $issuance->id, null, [
                'control_number' => $issuance->control_number,
                'document' => $document->code,
                'resident_id' => $resident->id,
                'resident' => $resident->full_name,
                'purpose' => $issuance->purpose,
                'fee' => $issuance->fee,
                'copies' => $issuance->copies,
            ]);

            return $issuance;
        });

        return redirect()
            ->route('certificates.print', $issuance)
            ->with('status', sprintf('Certificate %s issued for %s.', $issuance->control_number, $resident->full_name));
    }

    /** Standalone printable page — the view receives only the issuance. */
    public function print(CertificateIssuance $issuance): View
    {
        return view('certificates.print', ['issuance' => $issuance]);
    }

    /** Administrator voiding an issued certificate (admin middleware + guard). */
    public function void(VoidCertificateRequest $form, CertificateIssuance $issuance): RedirectResponse
    {
        if ($issuance->isVoided()) {
            return back()->with('error', sprintf('Certificate %s has already been voided.', $issuance->control_number));
        }

        $before = [
            'status' => $issuance->status,
            'void_reason' => $issuance->void_reason,
        ];

        $reason = $form->validated('void_reason');

        $issuance->update([
            'status' => CertificateIssuance::STATUS_VOIDED,
            'voided_at' => now(),
            'voided_by' => auth()->id(),
            'void_reason' => $reason !== null ? (string) $reason : null,
        ]);

        AuditLog::record('voided', 'certificate_issuance', $issuance->id, $before, [
            'status' => $issuance->status,
            'voided_at' => $issuance->voided_at?->toDateTimeString(),
            'void_reason' => $issuance->void_reason,
            'control_number' => $issuance->control_number,
        ]);

        return back()->with('status', sprintf('Certificate %s has been voided.', $issuance->control_number));
    }
}
