<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreCertificateTypeRequest;
use App\Http\Requests\Admin\UpdateCertificateTypeRequest;
use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Administrator-only catalog of the certificates the barangay issues
 * (CLR / COR / IND …): code, title, type, fee and status.
 */
class DocumentController extends Controller
{
    public function index(): View
    {
        return view('admin.certificate-types.index', [
            'documents' => Document::query()
                ->withCount('issuances')
                ->orderBy('code')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(StoreCertificateTypeRequest $request): RedirectResponse
    {
        $document = Document::create($request->validated());

        AuditLog::record('type_created', 'document', $document->id, null, $this->snapshot($document));

        return redirect()
            ->route('admin.certificate-types.index')
            ->with('status', sprintf('Certificate type %s added to the catalog.', $document->code));
    }

    public function update(UpdateCertificateTypeRequest $form, Document $document): RedirectResponse
    {
        $data = $form->validated();

        // Once a type has been issued its code is frozen: control numbers
        // already printed as "CLR-2026-0007" must keep their meaning.
        if ($document->issuances()->exists()) {
            unset($data['code']);
        }

        $before = $this->snapshot($document);

        $document->fill($data)->save();

        AuditLog::record('type_updated', 'document', $document->id, $before, $this->snapshot($document));

        return redirect()
            ->route('admin.certificate-types.index')
            ->with('status', sprintf('Certificate type %s updated.', $document->code));
    }

    public function destroy(Document $document): RedirectResponse
    {
        if ($document->issuances()->exists() || $document->requests()->exists()) {
            return redirect()
                ->route('admin.certificate-types.index')
                ->with('error', sprintf(
                    '%s (%s) already has issued certificates or online requests and cannot be deleted. Set it to Inactive instead.',
                    $document->code,
                    $document->title,
                ));
        }

        $before = $this->snapshot($document);
        $document->delete();

        AuditLog::record('type_deleted', 'document', $document->id, $before, null);

        return redirect()
            ->route('admin.certificate-types.index')
            ->with('status', sprintf('Certificate type %s deleted from the catalog.', $before['code']));
    }

    /**
     * The columns worth keeping in the audit trail.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Document $document): array
    {
        return $document->only(['code', 'title', 'description', 'document_type', 'fee', 'status']);
    }
}
