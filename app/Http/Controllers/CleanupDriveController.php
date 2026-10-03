<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CleanupDrive;
use App\Models\CleanupParticipant;
use App\Models\Concerns\Searchable;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CleanupDriveController extends Controller
{
    public function index(Request $request)
    {
        $query = CleanupDrive::with('purok')->withCount('participants')
            ->latest('scheduled_at')->latest('id');

        if ($request->filled('search')) {
            $search = Searchable::normalizeSearchTerm(mb_substr(strip_tags((string) $request->search), 0, 100));

            if ($search !== null && $search !== '') {
                // Shared scope: escapes LIKE wildcards and adds the
                // ESCAPE clause, mirroring the export dataset.
                $query->search($search, ['title', 'description']);
            }
        }

        if ($request->filled('status') && in_array($request->status, CleanupDrive::STATUSES, true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        $drives = $query->paginate(20)->withQueryString();
        $puroks = Purok::pluck('name', 'id');

        $page = max(1, (int) $request->input('page', 1));

        return view('cleanup.index', compact('drives', 'puroks'))
            ->with('i', ($page - 1) * $drives->perPage());
    }

    public function create()
    {
        return view('cleanup.create', [
            'puroks' => Purok::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->hasPermission('cleanup.manage'), 403);

        $validated = $this->validateDrive($request);

        // New drives always start Scheduled: the workflow advances them
        // through update() only, mirroring welfare intake forcing
        // Requested. Backfill an in-progress drive by creating it and then
        // moving it Scheduled -> Ongoing.
        $validated['status'] = 'Scheduled';
        $validated['created_by'] = Auth::id();

        $drive = DB::transaction(function () use ($request, $validated) {
            $drive = CleanupDrive::create($validated);

            AuditLog::record(
                'cleanup.created',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['drive_id' => $drive->id, 'title' => $drive->title],
            );

            return $drive;
        });

        return redirect()->route('cleanup.index')
            ->with('success', "Cleanup drive '{$drive->title}' scheduled successfully.");
    }

    /**
     * Single-drive detail with its sign-ups, kept route-agnostic on
     * purpose: no route points here yet. The venue-QR / public-sheet
     * workstream mounts its own routes on this action for reuse.
     */
    public function show(CleanupDrive $drive)
    {
        return view('cleanup.show', [
            'drive' => $drive->load(['purok', 'participants.resident'])->loadCount('participants'),
        ]);
    }

    public function edit(CleanupDrive $drive)
    {
        abort_unless(Auth::user()?->hasPermission('cleanup.manage'), 403);

        return view('cleanup.edit', [
            'drive' => $drive,
            'puroks' => Purok::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /**
     * Office logbook page: capped roster plus the staff walk-in form.
     * The printable sheet itself stays print-clean; the form lives in the
     * no-print toolbar. Resident lookup mirrors the office cap contract
     * (fetch 1001, show 1000, flag when capped).
     */
    public function logbook(CleanupDrive $drive)
    {
        $drive->load('purok');
        $total = $drive->participants()->count();
        $roster = $drive->participants()->with('resident.purok')->orderBy('id')->limit(200)->get();

        $lookup = Resident::where('status', 'Active')->orderBy('last_name')->orderBy('first_name')
            ->limit(1001)->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']);

        return view('cleanup.logbook', [
            'drive' => $drive,
            'roster' => $roster,
            'totalParticipants' => $total,
            'sheetUrl' => URL::signedRoute('cleanup.sheet', $drive),
            'residents' => $lookup->take(1000),
            'residentsCapped' => $lookup->count() > 1000,
        ]);
    }

    /**
     * Public signed sheet for the venue QR: aggregates only, never
     * volunteer names (mirrors the certificate verify page's no-PII rule).
     */
    public function sheet(CleanupDrive $drive)
    {
        $drive->load('purok')->loadCount('participants');

        return response()->view('cleanup.sheet', [
            'drive' => $drive,
            'signupCount' => $drive->participants()->count(),
            'attendedCount' => $drive->participants()->where('attended', true)->count(),
            'totalHours' => $drive->participants()->sum('hours'),
        ], 200);
    }

    public function update(Request $request, CleanupDrive $drive)
    {
        abort_unless($request->user()?->hasPermission('cleanup.manage'), 403);

        // Completed is terminal with NO reopen path: a finished drive is
        // history, so every edit — status or fields — is rejected here, not
        // just status changes. (Cancelled blocks only status moves, via
        // TRANSITIONS; its descriptive fields stay correctable.)
        if ($drive->status === 'Completed') {
            throw ValidationException::withMessages([
                'status' => 'This cleanup drive is Completed. Completed drives are terminal and cannot be edited.',
            ]);
        }

        $validated = $this->validateDrive($request);

        // The workflow is a state machine, not a free dropdown: anything
        // outside the whitelisted transitions is rejected.
        if ($drive->status !== $validated['status']
            && ! CleanupDrive::canTransition($drive->status, $validated['status'])
        ) {
            throw ValidationException::withMessages([
                'status' => "A drive with status '{$drive->status}' cannot move to '{$validated['status']}'.",
            ]);
        }

        DB::transaction(function () use ($request, $drive, $validated) {
            $drive->update($validated);

            AuditLog::record(
                'cleanup.updated',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['drive_id' => $drive->id, 'title' => $drive->title, 'status' => $drive->status],
            );
        });

        return redirect()->route('cleanup.index')
            ->with('success', "Cleanup drive '{$drive->title}' updated successfully.");
    }

    public function destroy(Request $request, CleanupDrive $drive)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $driveId = $drive->getKey();
        $title = $drive->title;

        DB::transaction(function () use ($request, $drive, $driveId, $title) {
            // Soft-delete: the sign-up history stays queryable and the
            // archive can restore the drive with its status intact.
            $drive->delete();

            AuditLog::record(
                'cleanup.deleted',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['drive_id' => $driveId, 'title' => $title],
            );
        });

        return redirect()->route('cleanup.index')
            ->with('success', "Cleanup drive '{$title}' deleted successfully.");
    }

    /**
     * Office-managed sign-up: attach a resident to a drive.
     * Finished drives (Completed/Cancelled) no longer accept sign-ups, and
     * a resident already on the roster gets a friendly validation error —
     * never a 500 from the UNIQUE(drive_id, resident_id) index.
     */
    public function join(Request $request, CleanupDrive $drive)
    {
        abort_unless($request->user()?->hasPermission('cleanup.manage'), 403);

        $validated = $request->validate([
            'resident_id' => ['required', 'integer', 'min:1', 'max:4294967295', Rule::exists('residents', 'id')],
        ], [
            'resident_id.required' => 'Select a resident to sign up for this drive.',
            'resident_id.integer' => 'Please select a registered resident from the list.',
            'resident_id.exists' => 'The selected resident is no longer available. Please choose another.',
        ]);

        $resident = Resident::withTrashed()->find($validated['resident_id']);

        if (! $resident || $resident->trashed() || $resident->status !== 'Active') {
            throw ValidationException::withMessages([
                'resident_id' => 'The selected resident is archived or inactive. Please choose an active resident.',
            ]);
        }

        if (in_array($drive->status, ['Completed', 'Cancelled'], true)) {
            throw ValidationException::withMessages([
                'drive' => "This drive is {$drive->status} and no longer accepts sign-ups.",
            ]);
        }

        try {
            DB::transaction(function () use ($drive, $resident) {
                // Row-lock the drive so concurrent sign-ups of the same
                // resident serialize here first; the UNIQUE index below is
                // the final guard and its violation is caught into a
                // friendly message, never a 500.
                CleanupDrive::whereKey($drive->getKey())->lockForUpdate()->firstOrFail();

                CleanupParticipant::create([
                    'drive_id' => $drive->getKey(),
                    'resident_id' => $resident->getKey(),
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'resident_id' => 'This resident is already signed up for this cleanup drive.',
                ]);
            }

            throw $exception;
        }

        return redirect()->route('cleanup.index')
            ->with('success', "{$resident->full_name} signed up for '{$drive->title}'.");
    }

    /**
     * Resident portal: upcoming drives the signed-in resident can join,
     * plus their summed volunteer hours.
     *
     * Kept here (not in a Resident* controller) on purpose: this slice may
     * only add portal methods to this controller, and the self sign-up
     * below shares the tx+lock+UNIQUE-friendly core of join().
     */
    public function portal(Request $request)
    {
        $resident = $this->portalResident($request);

        $drives = CleanupDrive::with('purok')
            ->whereIn('status', ['Scheduled', 'Ongoing'])
            ->latest('scheduled_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        // Roster lookup for the Join/Signed-up buttons, and the hours
        // total as ONE aggregate: only attended sign-ups carry hours.
        $joinedIds = CleanupParticipant::where('resident_id', $resident->getKey())
            ->pluck('drive_id')
            ->all();
        $hoursTotal = (float) CleanupParticipant::where('resident_id', $resident->getKey())
            ->where('attended', true)
            ->sum('hours');

        return view('resident.cleanups', [
            'resident' => $resident,
            'drives' => $drives,
            'joinedIds' => $joinedIds,
            'hoursTotal' => $hoursTotal,
        ]);
    }

    /**
     * Portal self sign-up: the signed-in resident joins a drive themselves.
     *
     * Same guards as the office walk-in join() (open drives only, one row
     * per resident via the UNIQUE(drive_id, resident_id) index caught into
     * a friendly message), but the resident comes from the session — there
     * is no resident_id input to tamper with.
     */
    public function joinSelf(Request $request, CleanupDrive $drive)
    {
        $resident = $this->portalResident($request);

        if (in_array($drive->status, ['Completed', 'Cancelled'], true)) {
            throw ValidationException::withMessages([
                'drive' => "This drive is {$drive->status} and no longer accepts sign-ups.",
            ]);
        }

        try {
            DB::transaction(function () use ($drive, $resident) {
                // Row-lock the drive so concurrent self sign-ups of the
                // same resident serialize here first; the UNIQUE index is
                // the final guard and its violation is caught into a
                // friendly message, never a 500.
                CleanupDrive::whereKey($drive->getKey())->lockForUpdate()->firstOrFail();

                CleanupParticipant::create([
                    'drive_id' => $drive->getKey(),
                    'resident_id' => $resident->getKey(),
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'drive' => 'You are already signed up for this cleanup drive.',
                ]);
            }

            throw $exception;
        }

        AuditLog::record(
            'resident.cleanup_joined',
            $request->user()?->getAuthIdentifier(),
            $request->user()?->email,
            $request->ip(),
            $request->userAgent(),
            ['drive_id' => $drive->getKey(), 'title' => $drive->title],
        );

        return back()->with('success', "You signed up for '{$drive->title}'. See you there!");
    }

    /**
     * The signed-in resident's own profile, or an abort. Mirrors the
     * private resident() helper on the sibling portal controllers (404
     * when staff never linked a record, 403 when it is archived).
     *
     * @return \App\Models\Resident
     */
    private function portalResident(Request $request)
    {
        $resident = $request->user()?->residentProfile;

        abort_if(! $resident, 404, 'No resident profile is linked to this account. Please contact the barangay office.');
        abort_if($resident->status !== 'Active', 403, 'This resident record is archived. Please contact the barangay office.');

        return $resident;
    }

    private function validateDrive(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|min:3|max:100',
            'description' => 'nullable|string|max:2000',
            'purok_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::exists('puroks', 'id')],
            'scheduled_at' => 'required|date|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'status' => ['required', Rule::in(CleanupDrive::STATUSES)],
        ], [
            'purok_id.integer' => 'Please select a purok from the list, or leave it blank.',
            'purok_id.exists' => 'The selected purok is no longer available. Please choose another.',
            'scheduled_at.after_or_equal' => 'The scheduled date must be on or after 1900-01-01.',
            'scheduled_at.before_or_equal' => 'The scheduled date must be on or before 2100-12-31.',
        ]);
    }

    /**
     * True when the query failure is a duplicate-key violation (double
     * sign-up racing past validation), on either MySQL (23000/Duplicate
     * entry) or SQLite (23000/UNIQUE constraint failed).
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        if ((string) $exception->getCode() === '23000') {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique') || str_contains($message, 'duplicate');
    }
}
