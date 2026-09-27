<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CertificateTypeController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(strip_tags((string) $request->input('search', '')), 0, 100);
        $status = (string) $request->input('status', '');

        $documents = Document::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['Active', 'Inactive', 'Draft'], true), fn ($query) => $query->where('status', $status))
            ->withCount(['issuances'])
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.certificate-types.index', compact('documents', 'search', 'status'));
    }

    public function create(): View
    {
        return view('admin.certificate-types.create');
    }

    public function edit(Document $document): View
    {
        return view('admin.certificate-types.edit', compact('document'));
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        }
        $validated = $request->validate($this->rules());
        $validated['code'] = strtoupper($validated['code']);
        $validated['created_by'] = Auth::id();
        $document = Document::create($validated);

        $this->audit($request, 'document.created', $document);

        return redirect()->route('admin.certificate-types.index')
            ->with('success', 'Document type created.');
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        }
        $validated = $request->validate($this->rules($document));
        $validated['code'] = strtoupper($validated['code']);
        if ($validated['code'] !== $document->code && $document->issuances()->exists()) {
            return back()->withErrors(['code' => 'A document code cannot change after it has been issued. Create a new version instead.']);
        }

        $validated['updated_by'] = Auth::id();
        $document->update($validated);
        $this->audit($request, 'document.updated', $document);

        return redirect()->route('admin.certificate-types.index')
            ->with('success', 'Document type updated.');
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        if ($document->issuances()->exists() || $document->requests()->exists()) {
            return back()->withErrors(['document' => 'This document type is referenced by historical records and cannot be removed.']);
        }

        $document->delete();
        $this->audit($request, 'document.archived', $document);

        return redirect()->route('admin.certificate-types.index')
            ->with('success', 'Unused document type archived.');
    }

    private function rules(?Document $document = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'min:2',
                'max:8',
                'regex:/^[A-Za-z0-9]+$/',
                // No `whereNull('deleted_at')` here on purpose: the rule queries
                // the table directly, so it already counts archived document
                // types. That has to match the hard unique index on `code`,
                // otherwise a re-created code passes validation and then fails
                // as an unhandled QueryException.
                Rule::unique('documents', 'code')->ignore($document?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:100'],
            'document_type' => ['required', 'in:Certificate,Permit,Clearance,ID,Other'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999'],
            'status' => ['required', 'in:Active,Inactive,Draft'],
        ];
    }

    private function audit(Request $request, string $event, Document $document): void
    {
        AuditLog::recordWithSubject(
            $event,
            $request->user()?->id,
            $request->user()?->email,
            $request->ip(),
            $request->userAgent(),
            'document',
            $document->id,
            $document->code,
            ['title' => $document->title, 'status' => $document->status],
        );
    }
}
