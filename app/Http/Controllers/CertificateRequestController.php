<?php

namespace App\Http\Controllers;

use App\Http\Requests\CertificateRequests\ApproveRequest;
use App\Http\Requests\CertificateRequests\RejectRequest;
use App\Models\AuditLog;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Document;
use App\Models\Resident;
use App\Models\SequenceCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Online certificate request queue: residents ask from the portal, the
 * office reviews. Approving creates a regular issuance (same control-number
 * flow as the counter) and sends the reviewer straight to the print page.
 */
class CertificateRequestController extends Controller
{
    private const DEFAULT_STATUSES = ['Pending', 'Approved', 'Rejected'];
    private const ALL_STATUSES = ['Pending', 'Approved', 'Rejected', 'Cancelled'];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status');

        $query = CertificateRequest::query()->with(['resident', 'document', 'reviewer', 'issuance']);

        if (in_array($status, self::ALL_STATUSES, true)) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', self::DEFAULT_STATUSES);
            $status = '';
        }

        return view('admin.certificate-requests.index', [
            'rows' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'pendingCount' => CertificateRequest::where('status', CertificateRequest::STATUS_PENDING)->count(),
            'statuses' => self::ALL_STATUSES,
            'status' => $status,
        ]);
    }

    /** Turn a pending request into an issued, printable certificate. */
    public function approve(ApproveRequest $form, CertificateRequest $request): RedirectResponse
    {
        $outcome = DB::transaction(function () use ($form, $request): array {
            $row = CertificateRequest::query()->lockForUpdate()->find($request->id);

            if ($row === null || $row->status !== CertificateRequest::STATUS_PENDING) {
                return ['error' => 'This request has already been processed.'];
            }

            $resident = $row->resident;

            if ($resident === null || $resident->status !== Resident::STATUS_ACTIVE) {
                return ['error' => 'The requesting resident is no longer active. Update the resident record before approving.'];
            }

            $document = $row->document;

            if ($document === null || $document->status !== 'Active') {
                return ['error' => 'The requested certificate type is no longer active. Choose another catalog entry first.'];
            }

            $fee = $form->validated('fee');

            $issuance = CertificateIssuance::create([
                'control_number' => SequenceCounter::nextControlNumber($document->code),
                'document_id' => $document->id,
                'resident_id' => $resident->id,
                'purpose' => $row->purpose,
                'fee' => $fee !== null ? round((float) $fee, 2) : (float) $document->fee,
                'copies' => max(1, (int) $row->copies),
                'recipient_snapshot' => CertificateIssuance::snapshotFor($resident),
                'status' => CertificateIssuance::STATUS_ISSUED,
                'issued_by' => auth()->id(),
                'issued_at' => now(),
            ]);

            $row->update([
                'status' => CertificateRequest::STATUS_APPROVED,
                'issuance_id' => $issuance->id,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            AuditLog::record('request_approved', 'certificate_request', $row->id, [
                'status' => CertificateRequest::STATUS_PENDING,
            ], [
                'status' => CertificateRequest::STATUS_APPROVED,
                'issuance_id' => $issuance->id,
                'control_number' => $issuance->control_number,
                'fee' => $issuance->fee,
            ]);

            return ['issuance' => $issuance, 'resident' => $resident];
        });

        if (isset($outcome['error'])) {
            return back()->with('error', $outcome['error']);
        }

        /** @var CertificateIssuance $issuance */
        $issuance = $outcome['issuance'];
        /** @var Resident $resident */
        $resident = $outcome['resident'];

        return redirect()
            ->route('certificates.print', $issuance)
            ->with('status', sprintf(
                'Request approved — certificate %s issued for %s.',
                $issuance->control_number,
                $resident->full_name,
            ));
    }

    /** Refuse a pending request, always with a reason for the resident. */
    public function reject(RejectRequest $form, CertificateRequest $request): RedirectResponse
    {
        $reason = (string) $form->validated('rejection_reason');

        $outcome = DB::transaction(function () use ($request, $reason): array {
            $row = CertificateRequest::query()->lockForUpdate()->find($request->id);

            if ($row === null || $row->status !== CertificateRequest::STATUS_PENDING) {
                return ['error' => 'This request has already been processed.'];
            }

            $before = ['status' => $row->status, 'rejection_reason' => $row->rejection_reason];

            $row->update([
                'status' => CertificateRequest::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLog::record('request_rejected', 'certificate_request', $row->id, $before, [
                'status' => $row->status,
                'rejection_reason' => $row->rejection_reason,
            ]);

            return ['row' => $row];
        });

        if (isset($outcome['error'])) {
            return back()->with('error', $outcome['error']);
        }

        return back()->with('status', sprintf(
            'Request from %s rejected — the resident will see the reason.',
            $outcome['row']->resident?->full_name ?? 'the resident',
        ));
    }
}
