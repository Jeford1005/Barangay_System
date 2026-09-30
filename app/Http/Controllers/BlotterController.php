<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\Concerns\Searchable;
use App\Models\Official;
use App\Models\Resident;
use App\Services\SequenceCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BlotterController extends Controller
{
    public function index(Request $request)
    {
        $query = Blotter::query()->latest('complaint_date')->latest('id');

        if ($request->filled('search')) {
            $search = Searchable::normalizeSearchTerm(mb_substr(strip_tags((string) $request->search), 0, 100)) ?? '';
            $like = '%'.self::escapeLike($search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("case_number LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("complainant_name LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("accused_name LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("complaint_type LIKE ? ESCAPE '\\'", [$like]);
            });
        }

        if ($request->filled('status') && in_array($request->status, ['Open', 'Pending', 'Resolved', 'Dismissed'], true)) {
            $query->where('status', $request->status);
        }

        $blotters = $query->paginate(20)->withQueryString();
        $openCount = Blotter::open()->count();

        $page = max(1, (int) $request->input('page', 1));

        return view('blotter.index', compact('blotters', 'openCount'))
            ->with('i', ($page - 1) * $blotters->perPage());
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
        return view('blotter.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $validated = $this->synchronizeLinkedResidentFields($this->validateBlotter($request));
        $validated['created_by'] = Auth::id();

        $blotter = DB::transaction(function () use ($request, $validated) {
            // The case number is server-generated: assign it explicitly, never
            // through mass assignment.
            $blotter = new Blotter($validated);
            $blotter->case_number = static::getNextCaseNumber();
            $blotter->save();

            AuditLog::record(
                'blotter.created',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['case_number' => $blotter->case_number],
            );

            return $blotter;
        });

        return redirect()->route('blotter.index')
            ->with('success', "Case {$blotter->case_number} recorded successfully.");
    }

    /**
     * Printable official case sheet for a single blotter record.
     * Standalone layout (no app chrome) so Ctrl+P yields a clean form.
     */
    public function printSheet(Blotter $blotter)
    {
        return view('blotter.print', [
            'blotter' => $blotter->load(['complainant', 'accused', 'officer', 'creator']),
        ]);
    }

    public function edit(Blotter $blotter)
    {
        return view('blotter.edit', array_merge(
            ['blotter' => $blotter],
            $this->formOptions($blotter),
        ));
    }

    public function update(Request $request, Blotter $blotter)
    {
        $validated = $this->synchronizeLinkedResidentFields($this->validateBlotter($request, $blotter), $blotter);
        $validated['updated_by'] = Auth::id();

        DB::transaction(function () use ($request, $blotter, $validated) {
            $blotter->update($validated);

            AuditLog::record(
                'blotter.updated',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['case_number' => $blotter->case_number],
            );
        });

        return redirect()->route('blotter.index')
            ->with('success', "Case {$blotter->case_number} updated successfully.");
    }

    public function destroy(Request $request, Blotter $blotter)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        DB::transaction(function () use ($request, $blotter) {
            $caseNumber = $blotter->case_number;
            $blotter->delete();

            AuditLog::record(
                'blotter.deleted',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['case_number' => $caseNumber],
            );
        });

        return redirect()->route('blotter.index')
            ->with('success', "Case {$blotter->case_number} deleted successfully.");
    }

    /**
     * The next case number in the barangay's BLTR-YYYY-#### sequence.
     * Takes a shared lock on the matching rows — call it inside a
     * transaction so concurrent clerks can't collide.
     */
    public static function getNextCaseNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "BLTR-{$year}-";

        $max = DB::table('blotter')
            ->where('case_number', 'like', $prefix.'%')
            ->max('case_number');
        $currentMaximum = $max ? (int) substr($max, strlen($prefix)) : 0;
        $next = app(SequenceCounter::class)->reserve('blotter', (int) $year, $currentMaximum);

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * A linked resident is the source of truth for party identity/contact data.
     * Blank IDs intentionally remain walk-in records and keep their free text.
     *
     * On update the sync only fires when the linked resident actually changed:
     * re-syncing on every save would silently discard the clerk's manual
     * edits to the name/address/phone fields.
     */
    private function synchronizeLinkedResidentFields(array $validated, ?Blotter $blotter = null): array
    {
        foreach ([
            'complainant_id' => ['complainant_name', 'complainant_address', 'complainant_phone'],
            'accused_id' => ['accused_name', 'accused_address', 'accused_phone'],
        ] as $idField => [$nameField, $addressField, $phoneField]) {
            if (blank($validated[$idField] ?? null)) {
                continue;
            }

            if ($blotter && (int) ($blotter->{$idField} ?? 0) === (int) $validated[$idField]) {
                continue;
            }

            $resident = Resident::withTrashed()->findOrFail($validated[$idField]);
            $validated[$nameField] = $resident->full_name;
            $validated[$addressField] = $resident->address;
            $validated[$phoneField] = $resident->phone_number;
        }

        return $validated;
    }

    private function validateBlotter(Request $request, ?Blotter $blotter = null): array
    {
        $rules = [
            'complainant_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::exists('residents', 'id')],
            'complainant_name' => 'required_without:complainant_id|nullable|string|max:255',
            'complainant_address' => 'nullable|string|max:255',
            'complainant_phone' => ['nullable', 'string', 'max:15', 'regex:/^(?=(?:.*\d){7,})\+?[0-9()\-\s]+$/'],
            'accused_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::exists('residents', 'id')],
            'accused_name' => 'required_without:accused_id|nullable|string|max:255',
            'accused_address' => 'nullable|string|max:255',
            'accused_phone' => ['nullable', 'string', 'max:15', 'regex:/^(?=(?:.*\d){7,})\+?[0-9()\-\s]+$/'],
            'complaint_type' => 'required|string|max:100',
            'complaint_subtype' => 'nullable|string|max:100',
            'complaint_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
            'complaint_time' => 'nullable|date_format:H:i',
            'alleged_offense' => 'required|string|max:2000',
            'status' => 'required|in:Open,Pending,Resolved,Dismissed',
            'disposition' => 'nullable|string|max:2000',
            'disposition_date' => 'nullable|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01|after_or_equal:complaint_date',
            'arrest_made' => 'required|in:Yes,No',
            'investigator' => 'nullable|string|max:255',
            'officer_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::exists('officials', 'id')->where('status', 'Active')],
            'remarks' => 'nullable|string|max:2000',
        ];

        $validated = $request->validate($rules, [
            'complainant_phone.regex' => 'The complainant phone number must contain at least 7 digits. Spaces, +, -, and parentheses are allowed.',
            'accused_phone.regex' => 'The respondent phone number must contain at least 7 digits. Spaces, +, -, and parentheses are allowed.',
            'complainant_id.integer' => 'Please select a registered resident from the list, or leave it blank for a walk-in.',
            'complainant_id.exists' => 'The selected resident is no longer available. Please choose another.',
            'accused_id.integer' => 'Please select a registered resident from the list, or leave it blank for a walk-in.',
            'accused_id.exists' => 'The selected resident is no longer available. Please choose another.',
            'officer_id.integer' => 'Please select a handling officer from the list, or leave it blank.',
            'officer_id.exists' => 'The selected officer is no longer available. Please choose another.',
            'complaint_date.before_or_equal' => 'The complaint date cannot be in the future.',
            'complaint_date.after_or_equal' => 'The complaint date must be on or after 1900-01-01.',
            'disposition_date.before_or_equal' => 'The disposition date cannot be in the future.',
            'disposition_date.after_or_equal' => 'The disposition date must be on or after the complaint date and 1900-01-01.',
        ]);

        $currentResidentIds = $blotter
            ? collect([$blotter->complainant_id, $blotter->accused_id])->filter()->map(fn ($id) => (int) $id)->all()
            : [];
        foreach (['complainant_id', 'accused_id'] as $field) {
            $residentId = $validated[$field] ?? null;
            if (blank($residentId)) {
                continue;
            }

            $resident = Resident::withTrashed()->find($residentId);
            $isCurrent = in_array((int) $residentId, $currentResidentIds, true);
            if (! $resident || ($resident->status !== 'Active' && ! $isCurrent)) {
                throw ValidationException::withMessages([
                    $field => 'The selected resident is archived or inactive. Please choose an active resident.',
                ]);
            }
        }

        // A case may not be closed without recording how it ended.
        if (in_array($validated['status'], ['Resolved', 'Dismissed'], true)) {
            $request->validate([
                'disposition' => 'required|string|max:2000',
                'disposition_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01|after_or_equal:complaint_date',
            ]);
        }

        // An open or pending case carries no close data: strip anything
        // submitted so a reopened case cannot keep its old disposition.
        if (in_array($validated['status'], ['Open', 'Pending'], true)) {
            $validated['disposition'] = null;
            $validated['disposition_date'] = null;
        }

        // Nobody may accuse themselves: the complainant and the accused must
        // be different people, whether linked residents or walk-in names.
        $complainantId = $validated['complainant_id'] ?? null;
        $accusedId = $validated['accused_id'] ?? null;
        if (filled($complainantId) && filled($accusedId) && (int) $complainantId === (int) $accusedId) {
            throw ValidationException::withMessages([
                'accused_id' => 'The complainant and the accused cannot be the same person.',
            ]);
        }
        if (blank($complainantId) && blank($accusedId)
            && filled($validated['complainant_name'] ?? null)
            && mb_strtolower(trim((string) $validated['complainant_name'])) === mb_strtolower(trim((string) ($validated['accused_name'] ?? '')))
        ) {
            throw ValidationException::withMessages([
                'accused_name' => 'The complainant and the accused cannot be the same person.',
            ]);
        }

        return $validated;
    }

    private function formOptions(?Blotter $blotter = null): array
    {
        $currentResidentIds = $blotter
            ? collect([$blotter->complainant_id, $blotter->accused_id])->filter()->map(fn ($id) => (int) $id)->all()
            : [];

        return [
            // Capped dropdown source: the form needs id/name/contact columns
            // for the options and autofill only, never the whole registry.
            'residents' => Resident::withTrashed()
                ->where(function ($query) use ($currentResidentIds) {
                    $query->where('status', 'Active')->whereNull('deleted_at');
                    if ($currentResidentIds !== []) {
                        $query->orWhereIn('id', $currentResidentIds);
                    }
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(1000)
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'address', 'phone_number', 'status', 'deleted_at']),
            'officials' => Official::active()
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'position']),
        ];
    }
}
