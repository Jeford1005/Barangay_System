<?php

namespace App\Http\Controllers;

use App\Models\CertificateRequest;
use App\Models\Document;
use App\Models\Official;
use App\Models\Resident;
use App\Services\QueueTracker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResidentCertificateRequestController extends Controller
{
    /**
     * The resident's own certificate requests + the request form data.
     */
    public function index(Request $request)
    {
        $resident = $this->requireProfile($request);

        $requests = CertificateRequest::query()
            ->where('resident_id', $resident->id)
            ->with(['document', 'issuance'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $documents = Document::active()->certificate()->orderBy('code')->get();

        // Queue position + honest ETA per pending card, counted in SQL
        // aggregates (never hydrated): same-document Pending rows for the
        // position, decided rows from the last 30 days for the pace.
        $queue = QueueTracker::lines(
            $requests->getCollection(),
            fn (CertificateRequest $row) => $row->document_id,
            fn (CertificateRequest $row) => $row->status === 'Pending',
            fn ($documentId) => CertificateRequest::query()
                ->where('document_id', $documentId)
                ->where('status', 'Pending'),
            fn (array $documentIds) => CertificateRequest::query()
                ->whereIn('document_id', $documentIds)
                ->whereIn('status', ['Approved', 'Rejected'])
                ->where('updated_at', '>=', now()->subDays(QueueTracker::WINDOW_DAYS))
                ->selectRaw('document_id as k, COUNT(*) as c')
                ->groupBy('document_id')
                ->pluck('c', 'k'),
        );

        return view('resident.requests', compact('requests', 'documents', 'queue'));
    }

    public function store(Request $request)
    {
        $resident = $this->requireProfile($request);

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
            'purpose' => ['required', 'string', 'max:255'],
            'copies' => ['required', 'integer', 'min:1', 'max:5'],
        ], [
            'document_id.integer' => 'Please select a certificate type from the list.',
            'document_id.exists' => 'The selected certificate type is no longer available. Please choose another.',
            'copies.integer' => 'Choose a whole number of copies from 1 to 5.',
        ]);

        // One open request per certificate type at a time keeps the queue clean.
        $open = CertificateRequest::where('resident_id', $resident->id)
            ->where('document_id', $validated['document_id'])
            ->whereIn('status', ['Pending'])
            ->exists();

        if ($open) {
            return back()->with('error', 'You already have a pending request for this certificate. Please wait for it to be reviewed.');
        }

        CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $validated['document_id'],
            'purpose' => $validated['purpose'],
            'copies' => $validated['copies'],
            'status' => 'Pending',
        ]);

        return redirect()
            ->route('resident.requests')
            ->with('success', 'Request submitted. The barangay office will review it and notify you by email.');
    }

    public function certificate(Request $request, CertificateRequest $certificateRequest)
    {
        $resident = $this->requireProfile($request);
        abort_unless($certificateRequest->resident_id === $resident->id, 403);
        abort_unless($certificateRequest->status === 'Approved' && $certificateRequest->issuance, 404);

        // A voided issuance is no longer an official document — keep the
        // resident-facing viewer under the same rule as the office print.
        if ($certificateRequest->issuance->status === 'Voided') {
            return redirect()->route('resident.requests')
                ->with('error', 'This certificate was voided by the barangay office. Please visit the hall for assistance.');
        }

        return view('certificates.print', [
            'issuance' => $certificateRequest->issuance->load(['document', 'resident.purok']),
            'punongBarangay' => Official::where('position', 'Punong Barangay')->active()->first(),
        ]);
    }

    public function cancel(Request $request, CertificateRequest $certificateRequest)
    {
        $resident = $this->requireProfile($request);
        $requestModel = $certificateRequest;

        // Residents may only cancel their own, still-pending requests.
        abort_unless($requestModel->resident_id === $resident->id, 403);
        abort_unless($requestModel->status === 'Pending', 422, 'Only pending requests can be cancelled.');

        $requestModel->update(['status' => 'Cancelled']);

        return back()->with('success', 'Request cancelled.');
    }

    private function requireProfile(Request $request): Resident
    {
        $resident = $request->user()?->residentProfile;

        abort_if(! $resident, 404, 'No resident profile is linked to this account. Please contact the barangay office.');
        abort_if($resident->status !== 'Active', 403, 'Archived resident profiles cannot request certificates.');

        return $resident;
    }
}
