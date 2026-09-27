<?php

namespace App\Http\Controllers;

use App\Http\Requests\Portal\StoreCertificateRequestRequest;
use App\Http\Requests\Portal\UpdateContactRequest;
use App\Models\AuditLog;
use App\Models\CertificateRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resident self-service portal (/my) — the resident's entire surface.
 *
 * Every action starts by resolving the caller's own resident record, so no
 * request can ever read or touch another resident's data: an unmatched
 * account gets a friendly page / flash, and records that are not theirs 404.
 */
class ResidentPortalController extends Controller
{
    /** Shown whenever the signed-in account has no matched resident record. */
    private const UNLINKED_MESSAGE = 'We could not match this account to a resident record yet. '
        .'Visit the barangay office (or make sure your resident record uses this same email) so we can link your profile.';

    /* ------------------------------------------------------------------ */
    /* Profile                                                             */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): View
    {
        return view('resident.portal', [
            'resident' => $request->user()?->linkedResident(),
        ]);
    }

    /**
     * The resident's own photo, served inline with locked-down headers so a
     * mislabelled upload can never be sniffed as markup on this origin.
     */
    public function photo(Request $request): StreamedResponse
    {
        $resident = $request->user()?->linkedResident();

        abort_if($resident === null, 404);

        $path = (string) $resident->photo_path;

        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            $disk = Storage::disk('public');
        }

        abort_unless($disk->exists($path), 404);

        try {
            $mime = (string) $disk->mimeType($path);
        } catch (\Throwable) {
            $mime = '';
        }

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'application/octet-stream';
        }

        return $disk->response($path, basename($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function updateContact(UpdateContactRequest $request): RedirectResponse
    {
        $auth = $request->user();
        $resident = $auth?->linkedResident();

        if ($resident === null) {
            return redirect()
                ->route('resident.portal')
                ->with('error', self::UNLINKED_MESSAGE);
        }

        $validated = $request->validated();

        $before = [];
        $after = [];
        $attributes = [];

        foreach (['phone', 'address', 'email'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = trim((string) $validated[$field]);
            $value = $value === '' ? null : $value;

            if ($value === $resident->{$field}) {
                continue;
            }

            $before[$field] = $resident->{$field};
            $after[$field] = $value;
            $attributes[$field] = $value;
        }

        $message = 'Profile updated.';

        if (array_key_exists('email', $attributes)) {
            if ($attributes['email'] !== null) {
                // Keep the login email in step with the resident record so
                // the account still matches its profile on the next visit.
                if ($attributes['email'] !== $auth->email) {
                    $auth->forceFill(['email' => $attributes['email']])->save();
                }

                $message = 'Profile updated. Your sign-in email is now '.$attributes['email'].'.';
            } else {
                $message = 'Profile updated. Your sign-in email ('.$auth->email.') was kept.';
            }
        }

        if ($before !== []) {
            $resident->fill($attributes)->save();

            AuditLog::record('contact_updated', 'resident', $resident->id, $before, $after);
        }

        return redirect()->route('resident.portal')->with('status', $message);
    }

    /* ------------------------------------------------------------------ */
    /* Certificate requests                                                */
    /* ------------------------------------------------------------------ */

    public function requests(Request $request): View
    {
        $resident = $request->user()?->linkedResident();

        if ($resident === null) {
            return redirect()
                ->route('resident.portal')
                ->with('error', self::UNLINKED_MESSAGE);
        }

        return view('resident.requests', [
            'resident' => $resident,
            'rows' => $resident->certificateRequests()
                ->with(['document', 'issuance'])
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'documents' => Document::query()
                ->active()
                ->whereIn('document_type', ['Certificate', 'Clearance'])
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function storeRequest(StoreCertificateRequestRequest $request): RedirectResponse
    {
        $resident = $request->user()?->linkedResident();

        if ($resident === null) {
            return redirect()
                ->route('resident.portal')
                ->with('error', self::UNLINKED_MESSAGE);
        }

        $validated = $request->validated();

        $alreadyPending = $resident->certificateRequests()
            ->where('document_id', $validated['document_id'])
            ->where('status', CertificateRequest::STATUS_PENDING)
            ->exists();

        if ($alreadyPending) {
            return redirect()
                ->route('resident.requests')
                ->with('error', 'You already have a pending request for this document.');
        }

        // Validated as Active + Certificate/Clearance by the form request.
        $document = Document::query()->active()->find($validated['document_id']);

        if ($document === null || ! in_array($document->document_type, ['Certificate', 'Clearance'], true)) {
            return redirect()
                ->route('resident.requests')
                ->with('error', 'That document is no longer available for online requests.');
        }

        $certificateRequest = $resident->certificateRequests()->create([
            'document_id' => $document->id,
            'purpose' => $validated['purpose'],
            'copies' => (int) $validated['copies'],
            'status' => CertificateRequest::STATUS_PENDING,
        ]);

        AuditLog::record('requested', 'certificate_request', $certificateRequest->id, null, [
            'document_id' => $document->id,
            'document' => $document->title,
            'purpose' => $certificateRequest->purpose,
            'copies' => (int) $validated['copies'],
            'status' => CertificateRequest::STATUS_PENDING,
        ]);

        return redirect()
            ->route('resident.requests')
            ->with('status', 'Your request has been submitted. The barangay office will review it shortly.');
    }

    public function cancelRequest(Request $request): RedirectResponse
    {
        $resident = $request->user()?->linkedResident();

        if ($resident === null) {
            return redirect()
                ->route('resident.portal')
                ->with('error', self::UNLINKED_MESSAGE);
        }

        // Scoping the query to the caller's own records makes a foreign id 404.
        $certificateRequest = $resident->certificateRequests()->find($request->route('request'));

        abort_if($certificateRequest === null, 404);

        if (! $certificateRequest->isPending()) {
            return redirect()
                ->route('resident.requests')
                ->with('error', 'Only pending requests can be cancelled.');
        }

        $certificateRequest->update(['status' => CertificateRequest::STATUS_CANCELLED]);

        AuditLog::record('cancelled', 'certificate_request', $certificateRequest->id, [
            'status' => CertificateRequest::STATUS_PENDING,
        ], [
            'status' => CertificateRequest::STATUS_CANCELLED,
        ]);

        return redirect()
            ->route('resident.requests')
            ->with('status', 'Your request has been cancelled.');
    }

    /** The printable certificate, only once it belongs to this resident and is approved. */
    public function downloadCertificate(Request $request): View|RedirectResponse
    {
        $resident = $request->user()?->linkedResident();

        if ($resident === null) {
            return redirect()
                ->route('resident.portal')
                ->with('error', self::UNLINKED_MESSAGE);
        }

        $certificateRequest = $resident->certificateRequests()
            ->with('issuance')
            ->find($request->route('request'));

        abort_if($certificateRequest === null, 404);
        abort_unless($certificateRequest->status === CertificateRequest::STATUS_APPROVED, 404);
        abort_if($certificateRequest->issuance === null, 404);

        return view('certificates.print', ['issuance' => $certificateRequest->issuance]);
    }
}
