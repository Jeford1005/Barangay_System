<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CertificateIssuance;
use App\Models\Concerns\Searchable;
use App\Models\Document;
use App\Models\Official;
use App\Models\Resident;
use App\Services\SequenceCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $query = CertificateIssuance::query()
            ->with(['document', 'resident.purok'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = Searchable::normalizeSearchTerm(mb_substr(strip_tags((string) $request->search), 0, 100)) ?? '';
            $like = '%'.self::escapeLike($search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("control_number LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("purpose LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereHas('resident', function ($r) use ($like) {
                        $r->whereRaw("first_name LIKE ? ESCAPE '\\'", [$like])
                            ->orWhereRaw("last_name LIKE ? ESCAPE '\\'", [$like]);
                    });
            });
        }

        if ($request->filled('status') && in_array($request->status, ['Issued', 'Voided'], true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('document_id')) {
            $query->where('document_id', (int) $request->document_id);
        }

        $issuances = $query->paginate(20)->withQueryString();
        $documents = Document::orderBy('code')->get();
        $todayCount = CertificateIssuance::whereBetween('created_at', [
            today()->startOfDay(),
            today()->endOfDay(),
        ])->count();

        return view('certificates.index', compact('issuances', 'documents', 'todayCount'));
    }

    /**
     * Escape LIKE wildcards so user input only ever matches literally.
     * To be used with an explicit `ESCAPE '\\'` clause (portable across
     * MySQL and SQLite).
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function create()
    {
        abort_unless(Auth::user()?->hasPermission('certificates.issue'), 403);

        return view('certificates.create', [
            'documents' => Document::active()->certificate()->orderBy('code')->get(),
            'residents' => Resident::active()
                ->with('purok:id,name')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'purok_id']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()?->hasPermission('certificates.issue'), 403);

        $validated = $request->validate([
            'document_id' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
                Rule::exists('documents', 'id')
                    ->where('status', 'Active')
                    ->whereIn('document_type', ['Certificate', 'Clearance']),
            ],
            'resident_id' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
                Rule::exists('residents', 'id')->where('status', 'Active'),
            ],
            'purpose' => 'required|string|max:255',
            'copies' => 'required|integer|min:1|max:5',
            'fee' => 'nullable|numeric|decimal:0,2|min:0|max:9999',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'document_id.integer' => 'Please select a certificate type from the list.',
            'document_id.exists' => 'The selected certificate type is no longer available. Please choose another.',
            'resident_id.integer' => 'Please select a resident from the list.',
            'resident_id.exists' => 'The selected resident is no longer available. Please choose another.',
            'copies.integer' => 'Enter a whole number of copies from 1 to 5.',
        ]);

        $issuance = DB::transaction(function () use ($request, $validated) {
            $document = Document::findOrFail($validated['document_id']);
            $resident = Resident::with('purok')->findOrFail($validated['resident_id']);
            $catalogFee = (float) $document->fee;
            // Only administrators may waive or adjust the unit fee at the
            // counter. Staff issuance always uses the posted catalog price.
            $feeOverridden = ! Auth::user()?->isStaff()
                && array_key_exists('fee', $validated)
                && $validated['fee'] !== null
                && round((float) $validated['fee'], 2) !== round($catalogFee, 2);
            $unitFee = Auth::user()?->isStaff()
                ? $document->fee
                : ($validated['fee'] ?? $document->fee);

            // The catalog/override fee is a per-copy unit price — the stored
            // amount is the total for the copies issued.
            $fee = round((float) $unitFee * (int) $validated['copies'], 2);

            $issuance = CertificateIssuance::create([
                'control_number' => static::getNextControlNumber($document->code),
                'document_id' => $document->id,
                'resident_id' => $resident->id,
                'recipient_snapshot' => CertificateIssuance::residentSnapshot($resident),
                'document_snapshot' => CertificateIssuance::documentSnapshot($document),
                'purpose' => $validated['purpose'],
                'copies' => $validated['copies'],
                // Administrators may waive or adjust the unit fee at the counter.
                // Staff issuance always uses the posted catalog price.
                'fee' => $fee,
                'status' => 'Issued',
                'remarks' => $validated['remarks'] ?? null,
                'issued_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);

            AuditLog::record(
                'certificate.issued',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                array_merge(
                    [
                        'control_number' => $issuance->control_number,
                        'resident_id' => $issuance->resident_id,
                        'document' => $document->title,
                    ],
                    $feeOverridden ? [
                        'fee_overridden' => true,
                        'catalog_fee' => round($catalogFee * (int) $validated['copies'], 2),
                        'fee' => $fee,
                    ] : [],
                ),
            );

            return $issuance;
        });

        return redirect()
            ->route('certificates.print', $issuance)
            ->with('success', "Certificate {$issuance->control_number} issued — printing.");
    }

    /**
     * Printable official certificate. Standalone layout (no app chrome) so
     * Ctrl+P yields a clean document, mirroring the blotter case sheet.
     */
    public function print(CertificateIssuance $issuance)
    {
        abort_unless(Auth::user()?->hasPermission('certificates.issue'), 403);

        // A voided certificate is no longer an official document — it cannot
        // be printed. The index hides the print action for voided rows; this
        // guard covers direct URLs and stale tabs.
        if ($issuance->status === 'Voided') {
            return redirect()->route('certificates.index')
                ->with('error', "Certificate {$issuance->control_number} is voided and cannot be printed.");
        }

        return view('certificates.print', [
            'issuance' => $issuance->load(['document', 'resident.purok']),
            'punongBarangay' => Official::where('position', 'Punong Barangay')->active()->first(),
        ]);
    }

    public function void(Request $request, CertificateIssuance $issuance)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $controlNumber = $issuance->control_number;

        // The row lock serializes concurrent void/restore clicks on the same
        // certificate; the status is re-checked inside the lock so the second
        // writer sees the first writer's decision. The audit entry is written
        // in the same transaction so a rollback can never leave a false audit.
        $voided = DB::transaction(function () use ($request, $issuance) {
            $locked = CertificateIssuance::query()
                ->whereKey($issuance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'Voided') {
                return false;
            }

            $locked->update([
                'status' => 'Voided',
                'voided_by' => Auth::id(),
                'voided_at' => now(),
            ]);

            AuditLog::record(
                'certificate.voided',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['control_number' => $locked->control_number],
            );

            return true;
        });

        if (! $voided) {
            return redirect()->route('certificates.index')
                ->with('error', "Certificate {$controlNumber} is already voided.");
        }

        return redirect()->route('certificates.index')
            ->with('success', "Certificate {$controlNumber} voided.");
    }

    /**
     * Restore (unvoid) a certificate. Voiding is no longer one-way: only
     * administrators may reverse it, and the reversal is audit-logged like
     * the void itself.
     */
    public function restore(Request $request, CertificateIssuance $issuance)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $controlNumber = $issuance->control_number;

        // Same race protection as void(): row lock, status re-checked inside
        // the lock, audit written in the same transaction.
        $restored = DB::transaction(function () use ($request, $issuance) {
            $locked = CertificateIssuance::query()
                ->whereKey($issuance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'Issued') {
                return false;
            }

            $locked->update([
                'status' => 'Issued',
                'voided_by' => null,
                'voided_at' => null,
            ]);

            AuditLog::record(
                'certificate.restored',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['control_number' => $locked->control_number],
            );

            return true;
        });

        if (! $restored) {
            return redirect()->route('certificates.index')
                ->with('error', "Certificate {$controlNumber} is already active.");
        }

        return redirect()->route('certificates.index')
            ->with('success', "Certificate {$controlNumber} restored.");
    }

    /**
     * The next control number in the barangay's <CODE>-YYYY-#### sequence,
     * e.g. CLR-2026-0007. The max() read and the counter reservation run in
     * one transaction so concurrent clerks serialize on the counter row
     * (same pattern as blotter case numbers) instead of colliding.
     */
    public static function getNextControlNumber(string $documentCode): string
    {
        $documentCode = strtoupper(trim($documentCode));
        if (! preg_match('/^[A-Z0-9]{2,8}$/', $documentCode)) {
            throw new InvalidArgumentException('Certificate codes must contain 2–8 letters or numbers.');
        }

        $year = now()->format('Y');
        $prefix = $documentCode."-{$year}-";

        return DB::transaction(function () use ($documentCode, $year, $prefix) {
            $max = DB::table('certificate_issuances')
                ->where('control_number', 'like', $prefix.'%')
                ->max('control_number');
            $currentMaximum = $max ? (int) substr($max, strlen($prefix)) : 0;
            $next = app(SequenceCounter::class)->reserve('certificate:'.$documentCode, (int) $year, $currentMaximum);

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }, 3);
    }
}
