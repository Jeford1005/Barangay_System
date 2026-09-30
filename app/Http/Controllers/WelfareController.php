<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Concerns\Searchable;
use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WelfareController extends Controller
{
    public function index(Request $request)
    {
        $query = Welfare::query()->latest('request_date')->latest('id');

        if ($request->filled('search')) {
            $search = Searchable::normalizeSearchTerm(mb_substr(strip_tags((string) $request->search), 0, 100)) ?? '';
            $like = '%'.self::escapeLike($search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("beneficiary_name LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("program_name LIKE ? ESCAPE '\\'", [$like]);
            });
        }

        if ($request->filled('status') && in_array($request->status, ['Requested', 'Under Review', 'Approved', 'Denied', 'Released'], true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assistance_type') && in_array($request->assistance_type, ['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other'], true)) {
            $query->where('assistance_type', $request->assistance_type);
        }

        $welfares = $query->paginate(20);
        $pendingCount = Welfare::requested()->count();
        $pendingAmount = Welfare::approved()->sum('approved_amount');

        return view('welfare.index', compact('welfares', 'pendingCount', 'pendingAmount'))
            ->with('i', ($request->input('page', 1) - 1) * $welfares->perPage());
    }

    public function create()
    {
        return view('welfare.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        // Intake records requests only: validation still runs on exactly
        // what was submitted (so bad money/status input errors instead of
        // passing silently), then approval, release, denial, and monetary
        // decisions are stripped before the row is written. They happen
        // through the approve-gated update path — even for administrators.
        abort_unless($request->user()?->hasPermission('welfare.intake'), 403);

        $validated = $this->synchronizeLinkedResidentFields($this->validateWelfare($request));

        $validated['status'] = 'Requested';
        unset($validated['approved_amount'], $validated['approval_date'], $validated['release_date']);

        $welfare = DB::transaction(function () use ($request, $validated) {
            $welfare = Welfare::create($validated);

            // Intake never carries money or decision dates: record the
            // zeroed state explicitly, never through mass assignment.
            $welfare->approved_amount = 0;
            $welfare->approval_date = null;
            $welfare->release_date = null;
            $welfare->save();

            AuditLog::record(
                'welfare.created',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['beneficiary' => $welfare->beneficiary_name, 'program' => $welfare->program_name],
            );

            return $welfare;
        });

        return redirect()->route('welfare.index')
            ->with('success', "Assistance request for {$welfare->beneficiary_name} recorded successfully.");
    }

    public function edit(Welfare $welfare)
    {
        abort_unless(Auth::user()?->hasPermission('welfare.approve'), 403);

        return view('welfare.edit', array_merge(
            ['welfare' => $welfare],
            $this->formOptions($welfare),
        ));
    }

    public function update(Request $request, Welfare $welfare)
    {
        abort_unless($request->user()?->hasPermission('welfare.approve'), 403);

        $validated = $this->synchronizeLinkedResidentFields($this->validateWelfare($request, $welfare));

        // The workflow is a state machine, not a free dropdown: anything
        // outside the whitelisted transitions is rejected, so money can only
        // reach Released through Approved — never straight from intake,
        // review, or denial.
        if ($welfare->status !== $validated['status']
            && ! Welfare::canTransition($welfare->status, $validated['status'])
        ) {
            throw ValidationException::withMessages([
                'status' => "A request with status '{$welfare->status}' cannot move to '{$validated['status']}'.",
            ]);
        }

        // Committed money must never evaporate through a status edit: leaving
        // Approved/Released for an unapproved state while an amount is booked
        // would erase the totals the reports already counted.
        if (in_array($welfare->status, ['Approved', 'Released'], true)
            && in_array($validated['status'], ['Requested', 'Under Review', 'Denied'], true)
            && (float) $welfare->approved_amount > 0
        ) {
            throw ValidationException::withMessages([
                'status' => 'This request carries a committed amount. Clear the approved amount (and its dates) before moving it back to an unapproved status.',
            ]);
        }

        DB::transaction(function () use ($request, $welfare, $validated) {
            // Money and decision dates are privileged: fill the ordinary
            // fields, then assign these explicitly.
            $money = [
                'approved_amount' => $validated['approved_amount'] ?? 0,
                'approval_date' => $validated['approval_date'] ?? null,
                'release_date' => $validated['release_date'] ?? null,
            ];
            unset($validated['approved_amount'], $validated['approval_date'], $validated['release_date']);

            $welfare->fill($validated);
            $welfare->approved_amount = $money['approved_amount'];
            $welfare->approval_date = $money['approval_date'];
            $welfare->release_date = $money['release_date'];
            $welfare->save();

            AuditLog::record(
                'welfare.updated',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['beneficiary' => $welfare->beneficiary_name, 'program' => $welfare->program_name],
            );
        });

        return redirect()->route('welfare.index')
            ->with('success', "Assistance request for {$welfare->beneficiary_name} updated successfully.");
    }

    public function destroy(Request $request, Welfare $welfare)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        // Committed money must not vanish from the totals: an Approved or
        // Released request cannot be deleted (even softly) while it carries
        // an amount. Settle or zero it through the approve-gated update path
        // first; other states soft-delete with a full audit trail as before.
        if (in_array($welfare->status, ['Approved', 'Released'], true) && (float) $welfare->approved_amount > 0) {
            return back()->withErrors([
                'welfare' => "This request is {$welfare->status} with a committed amount of {$welfare->approved_amount}. It cannot be deleted while the amount is booked.",
            ]);
        }

        $beneficiary = $welfare->beneficiary_name;

        DB::transaction(function () use ($request, $welfare) {
            $welfare->delete();

            AuditLog::record(
                'welfare.deleted',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['beneficiary' => $welfare->beneficiary_name, 'program' => $welfare->program_name],
            );
        });

        return redirect()->route('welfare.index')
            ->with('success', "Assistance request for {$beneficiary} deleted successfully.");
    }

    private function synchronizeLinkedResidentFields(array $validated): array
    {
        if (blank($validated['beneficiary_id'] ?? null)) {
            return $validated;
        }

        $resident = Resident::withTrashed()->findOrFail($validated['beneficiary_id']);
        $validated['beneficiary_name'] = $resident->full_name;
        $validated['beneficiary_address'] = $resident->address;
        $validated['beneficiary_phone'] = $resident->phone_number;

        return $validated;
    }

    private function validateWelfare(Request $request, ?Welfare $welfare = null): array
    {
        $validated = $request->validate([
            'beneficiary_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::exists('residents', 'id')],
            'beneficiary_name' => 'required_without:beneficiary_id|nullable|string|max:255',
            'beneficiary_address' => 'nullable|string|max:255',
            'beneficiary_phone' => ['nullable', 'string', 'max:15', 'regex:/^(?=(?:.*\d){7,})\+?[0-9()\-\s]+$/'],
            'assistance_type' => 'required|in:Financial,Food,Medical,Educational,Housing,Other',
            'program_name' => 'required|string|max:255',
            'program_description' => 'nullable|string|max:2000',
            'requested_amount' => 'required|numeric|decimal:0,2|gt:0|max:99999999.99',
            'approved_amount' => 'nullable|numeric|decimal:0,2|min:0|max:99999999.99',
            'status' => 'required|in:Requested,Under Review,Approved,Denied,Released',
            'request_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
            'approval_date' => 'nullable|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
            'release_date' => 'nullable|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01|after_or_equal:approval_date',
            'remarks' => 'nullable|string|max:2000',
        ], [
            'beneficiary_phone.regex' => 'The beneficiary phone number must contain at least 7 digits. Spaces, +, -, and parentheses are allowed.',
            'beneficiary_id.integer' => 'Please select a registered resident from the list, or leave it blank for a walk-in.',
            'beneficiary_id.exists' => 'The selected resident is no longer available. Please choose another.',
            'request_date.before_or_equal' => 'The request date cannot be in the future.',
            'request_date.after_or_equal' => 'The request date must be on or after 1900-01-01.',
            'approval_date.before_or_equal' => 'The approval date cannot be in the future.',
            'approval_date.after_or_equal' => 'The approval date must be on or after 1900-01-01.',
            'release_date.before_or_equal' => 'The release date cannot be in the future.',
            'release_date.after_or_equal' => 'The release date must be on or after the approval date and 1900-01-01.',
        ]);

        $residentId = $validated['beneficiary_id'] ?? null;
        if (filled($residentId)) {
            $resident = Resident::withTrashed()->find($residentId);
            $isCurrent = $welfare && (int) $welfare->beneficiary_id === (int) $residentId;
            if (! $resident || ($resident->status !== 'Active' && ! $isCurrent)) {
                throw ValidationException::withMessages([
                    'beneficiary_id' => 'The selected resident is archived or inactive. Please choose an active resident.',
                ]);
            }
        }

        // The column is NOT NULL DEFAULT 0: an unapproved request stores 0,
        // never NULL (browsers submit the empty field as an empty string).
        $validated['approved_amount'] = $validated['approved_amount'] ?? 0;

        // Workflow integrity: money needs an approval date, release needs a date.
        if ($validated['status'] === 'Approved') {
            $request->validate([
                'approval_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
                'approved_amount' => 'required|numeric|decimal:0,2|gt:0|max:99999999.99',
            ]);
        }

        if ($validated['status'] === 'Released') {
            $request->validate([
                'approval_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
                'approved_amount' => 'required|numeric|decimal:0,2|gt:0|max:99999999.99',
                'release_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01|after_or_equal:approval_date',
            ]);
        }

        // An approval can never exceed the request. Without this the office
        // could approve a peso amount against a zero request, and the printed
        // welfare report totals it as disbursed.
        if ((float) $validated['approved_amount'] > (float) $validated['requested_amount']) {
            throw ValidationException::withMessages([
                'approved_amount' => 'The approved amount cannot be greater than the requested amount.',
            ]);
        }

        // A denied request must record why, and must not carry money or dates
        // that imply funds moved: decision dates are cleared on deny.
        if ($validated['status'] === 'Denied') {
            if (blank($validated['remarks'] ?? null)) {
                throw ValidationException::withMessages([
                    'remarks' => 'Record a reason when denying an assistance request.',
                ]);
            }

            if ((float) $validated['approved_amount'] > 0) {
                throw ValidationException::withMessages([
                    'approved_amount' => 'A denied request cannot carry an approved amount.',
                ]);
            }

            $validated['approved_amount'] = 0;
            $validated['approval_date'] = null;
            $validated['release_date'] = null;
        }

        // Money and decision dates exist only after approval: a request that
        // is still Requested or Under Review must not carry either.
        if (in_array($validated['status'], ['Requested', 'Under Review'], true)) {
            if ((float) $validated['approved_amount'] > 0) {
                throw ValidationException::withMessages([
                    'approved_amount' => 'An unapproved request cannot carry an approved amount.',
                ]);
            }

            $validated['approved_amount'] = 0;
            $validated['approval_date'] = null;
            $validated['release_date'] = null;
        }

        return $validated;
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

    private function formOptions(?Welfare $welfare = null): array
    {
        $currentResidentIds = $welfare && $welfare->beneficiary_id ? [(int) $welfare->beneficiary_id] : [];

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
        ];
    }
}
