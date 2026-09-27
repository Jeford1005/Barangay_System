<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Notifications\CertificateRequestDecisionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CertificateRequestAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = CertificateRequest::query()->with(['resident.purok', 'document', 'issuance']);

        if ($request->filled('status') && in_array($request->status, ['Pending', 'Approved', 'Rejected', 'Cancelled'], true)) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['Pending', 'Approved', 'Rejected']);
        }

        $requests = $query->latest('id')->paginate(20)->withQueryString();
        $pendingCount = CertificateRequest::pending()->count();

        return view('admin.certificate-requests', compact('requests', 'pendingCount'));
    }

    public function approve(Request $request, CertificateRequest $certificateRequest)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            // The clerk may adjust the counter fee (e.g. waive for indigents).
            'fee' => 'nullable|numeric|decimal:0,2|min:0|max:9999',
        ]);

        $issuance = DB::transaction(function () use ($request, $certificateRequest, $validated) {
            // The status is re-read under a row lock *inside* the transaction.
            // Two clerks (or a double-clicked button) must not both consume a
            // control number and orphan the first issuance.
            $requestModel = CertificateRequest::whereKey($certificateRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($requestModel->status === 'Pending', 422, 'Only pending requests can be approved.');

            $document = $requestModel->document;
            $resident = $requestModel->resident()->with('purok')->firstOrFail();

            if ($resident->status !== 'Active') {
                throw ValidationException::withMessages([
                    'resident_id' => 'Archived resident profiles cannot receive certificate approvals.',
                ]);
            }

            $issuance = CertificateIssuance::create([
                'control_number' => CertificateController::getNextControlNumber($document->code),
                'document_id' => $document->id,
                'resident_id' => $resident->id,
                'recipient_snapshot' => CertificateIssuance::residentSnapshot($resident),
                'document_snapshot' => CertificateIssuance::documentSnapshot($document),
                'purpose' => $requestModel->purpose,
                'copies' => $requestModel->copies,
                'fee' => $validated['fee'] ?? $document->fee,
                'status' => 'Issued',
                'remarks' => 'Issued from online request #'.$requestModel->id,
                'issued_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);

            $requestModel->update([
                'status' => 'Approved',
                'issuance_id' => $issuance->id,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            AuditLog::record(
                'certificate.request_approved',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                [
                    'request_id' => $requestModel->id,
                    'control_number' => $issuance->control_number,
                    'resident_id' => $requestModel->resident_id,
                ],
            );

            return [$issuance, $requestModel];
        });

        [$issuance, $requestModel] = $issuance;

        $requestModel->load('resident.user');
        $requestModel->resident?->user?->notify(
            new CertificateRequestDecisionNotification($requestModel, true),
        );

        return redirect()
            ->route('certificates.print', $issuance)
            ->with('success', "Request approved — certificate {$issuance->control_number} is ready to print.");
    }

    public function reject(Request $request, CertificateRequest $certificateRequest)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $requestModel = DB::transaction(function () use ($request, $certificateRequest, $validated) {
            $requestModel = CertificateRequest::whereKey($certificateRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($requestModel->status === 'Pending', 422, 'Only pending requests can be rejected.');

            $requestModel->update([
                'status' => 'Rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            AuditLog::record(
                'certificate.request_rejected',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                [
                    'request_id' => $requestModel->id,
                    'resident_id' => $requestModel->resident_id,
                    'reason' => $validated['rejection_reason'],
                ],
            );

            return $requestModel;
        });

        $requestModel->load('resident.user');
        $requestModel->resident?->user?->notify(
            new CertificateRequestDecisionNotification($requestModel, false),
        );

        return redirect()
            ->route('admin.certificate-requests.index')
            ->with('success', 'Request rejected. The resident has been notified by email.');
    }
}
