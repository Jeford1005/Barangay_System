<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Services\HouseholdResidentSync;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResidentController extends Controller
{
    public function index(Request $request)
    {
        $query = Resident::with(['purok', 'household']);

        // Search - shared scope: separate column matches (no CONCAT, so the
        // name indexes stay usable) with LIKE-escaping and quote
        // normalization handled inside.
        if ($request->filled('search')) {
            $search = mb_substr(strip_tags((string) $request->search), 0, 100);
            $query->search($search, ['first_name', 'last_name']);
        }

        // Filter by purok
        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        // Filter by household
        if ($request->filled('household_id')) {
            $query->where('household_id', $request->integer('household_id'));
        }

        // Filter by status - whitelisted so an unknown value filters nothing
        // instead of silently returning an empty list.
        if ($request->filled('status') && in_array($request->string('status')->toString(), ['Active', 'Archived'], true)) {
            $query->where('status', $request->string('status')->toString());
        }

        $residents = $query->orderBy('last_name')->paginate(20)->withQueryString();

        $puroks = Purok::pluck('name', 'id');
        $households = Household::pluck('household_code', 'id');

        $page = max(1, (int) $request->input('page', 1));

        return view('resident.index', compact('residents', 'puroks', 'households'))
            ->with('i', ($page - 1) * $residents->perPage());
    }

    /**
     * Printable resident directory grouped by purok with per-purok counts.
     * Standalone document layout (no app chrome), active residents only,
     * purok-less residents listed under their own heading so nobody is
     * silently dropped from the record.
     */
    public function directory()
    {
        // The roster page is paginated (500 per page) with screen/print
        // columns only, so a large barangay never hydrates every resident at
        // once. The summary counts below still cover the full directory.
        $page = Resident::active()
            ->with('purok:id,name')
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'birth_date', 'phone_number', 'voter_status', 'purok_id'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(500)
            ->withQueryString();

        $grouped = $page->getCollection()
            ->groupBy(fn ($r) => $r->purok?->name ?? 'No Purok Assigned')
            ->sortBy(fn ($rows, $key) => $key === 'No Purok Assigned' ? PHP_INT_MAX : $key);

        // Full-directory counts for the summary table and certification line.
        $counts = Resident::active()
            ->selectRaw('purok_id, COUNT(*) as c')
            ->groupBy('purok_id')
            ->get();

        $purokCounts = Purok::orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(function (string $name, int $id) use ($counts) {
                $count = (int) ($counts->first(fn ($row) => (int) $row->purok_id === $id)?->c ?? 0);

                return $count > 0 ? [$name => $count] : [];
            });

        $unassigned = (int) ($counts->first(fn ($row) => $row->purok_id === null)?->c ?? 0);
        if ($unassigned > 0) {
            $purokCounts->put('No Purok Assigned', $unassigned);
        }

        return view('residents.directory', [
            'grouped' => $grouped,
            'purokCounts' => $purokCounts,
            'total' => $page->total(),
        ]);
    }

    public function create()
    {
        abort_unless(Auth::user()?->hasPermission('residents.manage'), 403);

        $puroks = Purok::pluck('name', 'id');
        $households = Household::pluck('household_code', 'id');

        return view('resident.create', compact('puroks', 'households'));
    }

    public function store(Request $request, HouseholdResidentSync $householdResidentSync)
    {
        abort_unless(Auth::user()?->hasPermission('residents.manage'), 403);

        $validated = $this->validateResident($request);

        // The database stores nationality as NOT NULL with a Filipino default;
        // normalize a cleared optional field before Eloquent persists it.
        $validated['nationality'] = ($validated['nationality'] ?? null) ?: 'Filipino';
        if (Auth::user()?->isStaff()) {
            $validated['status'] = 'Active';
        }
        // The upload is captured, not stored: the file lands on disk inside
        // the transaction under a random hashed name, and is deleted again if
        // the transaction rolls back, so a failed save never leaves an orphan.
        $photoFile = $request->hasFile('photo') ? $request->file('photo') : null;
        unset($validated['photo']);
        $validated['created_by'] = Auth::id();

        $newPhotoPath = null;

        DB::beginTransaction();
        try {
            $resident = Resident::create($validated);

            if ($photoFile) {
                $newPhotoPath = $photoFile->storeAs('residents', $photoFile->hashName(), 'local');
                $resident->update(['photo' => $newPhotoPath]);
            }

            $householdResidentSync->syncResidentAfterSave($resident);

            AuditLog::recordWithSubject(
                'resident.created',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                'resident',
                $resident->id,
                $resident->full_name,
            );

            DB::commit();

            return redirect()->route('residents.index')
                ->with('success', 'Resident registered successfully.');
        } catch (ValidationException $e) {
            DB::rollBack();
            if ($newPhotoPath) {
                Storage::disk('local')->delete($newPhotoPath);
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($newPhotoPath) {
                Storage::disk('local')->delete($newPhotoPath);
            }

            return back()->withErrors(['error' => 'Failed to register resident.'])->withInput();
        }
    }

    public function edit(Resident $resident)
    {
        abort_unless(Auth::user()?->hasPermission('residents.manage'), 403);

        $puroks = Purok::pluck('name', 'id');
        $households = Household::pluck('household_code', 'id');

        return view('resident.edit', compact('resident', 'puroks', 'households'));
    }

    public function update(Request $request, Resident $resident, HouseholdResidentSync $householdResidentSync)
    {
        abort_unless(Auth::user()?->hasPermission('residents.manage'), 403);

        $previousHouseholdId = $resident->household_id;
        $before = Arr::only($resident->getAttributes(), array_keys($this->validateResidentFields($resident->id)));
        $validated = $this->validateResident($request, $resident->id);
        $validated['nationality'] = ($validated['nationality'] ?? null) ?: 'Filipino';
        // The upload is captured, not stored: the file lands on disk inside
        // the transaction under a random hashed name, and is deleted again if
        // the transaction rolls back, so a failed save never leaves an orphan.
        $photoFile = $request->hasFile('photo') ? $request->file('photo') : null;
        $oldPhoto = $resident->photo;
        unset($validated['photo']);

        // Status changes are an administrator-only lifecycle action. Staff
        // may correct ordinary profile fields without archiving or restoring.
        if (Auth::user()?->isStaff()) {
            $validated['status'] = $resident->status;
        }

        // A household head must never be moved away silently: the sync layer
        // would otherwise strip the headship and clear the old household's
        // head pointer without telling anyone. Make the clerk reassign heads
        // explicitly first.
        $newHouseholdId = array_key_exists('household_id', $validated)
            ? ($validated['household_id'] === null ? null : (int) $validated['household_id'])
            : $previousHouseholdId;
        if ($resident->is_household_head
            && $previousHouseholdId
            && $newHouseholdId !== $previousHouseholdId
        ) {
            throw ValidationException::withMessages([
                'household_id' => 'This resident is the head of the current household. Assign a new head (or remove the head assignment) before moving them to another household.',
            ]);
        }

        $validated['updated_by'] = Auth::id();

        $newPhotoPath = null;

        DB::beginTransaction();
        try {
            $resident->update($validated);

            if ($photoFile) {
                $newPhotoPath = $photoFile->storeAs('residents', $photoFile->hashName(), 'local');
                $resident->update(['photo' => $newPhotoPath]);
            }

            $householdResidentSync->syncResidentAfterSave($resident, $previousHouseholdId);

            // The sync layer demotes/promotes head flags with direct queries;
            // refresh so the change audit below sees the row as stored.
            $resident->refresh();

            // Record which fields actually moved, so the audit trail can answer
            // "who changed this resident's address" rather than only that a
            // save happened.
            $changed = collect(array_keys($before))
                ->reject(fn (string $field) => (string) $before[$field] === (string) $resident->getAttribute($field))
                ->values()
                ->all();

            if ($changed !== []) {
                AuditLog::recordWithSubject(
                    'resident.updated',
                    Auth::id(),
                    Auth::user()?->email,
                    $request->ip(),
                    $request->userAgent(),
                    'resident',
                    $resident->id,
                    $resident->full_name,
                    ['fields' => $changed],
                );
            }

            DB::commit();

            if ($newPhotoPath && $oldPhoto && $oldPhoto !== $newPhotoPath) {
                Storage::disk('local')->delete($oldPhoto);
                Storage::disk('public')->delete($oldPhoto);
            }

            return redirect()->route('residents.index')
                ->with('success', 'Resident information updated successfully.');
        } catch (ValidationException $e) {
            DB::rollBack();
            if ($newPhotoPath) {
                Storage::disk('local')->delete($newPhotoPath);
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($newPhotoPath) {
                Storage::disk('local')->delete($newPhotoPath);
            }

            return back()->withErrors(['error' => 'Failed to update resident.'])->withInput();
        }
    }

    public function archive(Resident $resident, HouseholdResidentSync $householdResidentSync)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        DB::beginTransaction();
        try {
            $previousHouseholdId = $resident->household_id;
            $resident->is_household_head = false;
            $resident->status = 'Archived';
            $resident->updated_by = Auth::id();
            $resident->save();
            $householdResidentSync->syncResidentAfterSave($resident, $previousHouseholdId);

            if ($resident->user) {
                $resident->user->update([
                    'suspended_at' => now(),
                    'suspended_by' => Auth::id(),
                    'suspension_reason' => 'Resident record archived by the barangay office.',
                ]);
                DB::table('password_reset_tokens')->where('email', $resident->user->email)->delete();
                DB::table('sessions')->where('user_id', $resident->user->id)->delete();
            }

            AuditLog::record(
                'resident.archived',
                Auth::id(),
                Auth::user()?->email,
                request()->ip(),
                request()->userAgent(),
                ['resident_id' => $resident->id],
            );

            DB::commit();

            return redirect()->route('residents.index')
                ->with('success', 'Resident archived successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to archive resident.']);
        }
    }

    public function restore(Resident $resident)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        DB::beginTransaction();
        try {
            $resident->status = 'Active';
            $resident->updated_by = Auth::id();
            $resident->save();

            AuditLog::record(
                'resident.restored',
                Auth::id(),
                Auth::user()?->email,
                request()->ip(),
                request()->userAgent(),
                ['resident_id' => $resident->id],
            );

            DB::commit();

            return redirect()->route('residents.index')
                ->with('success', 'Resident restored successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to restore resident.']);
        }
    }

    private function validateResident(Request $request, $residentId = null)
    {
        return $request->validate($this->validateResidentFields($residentId), [
            'phone_number.regex' => 'The phone number must contain at least 7 digits. Spaces, +, -, and parentheses are allowed.',
            'blood_type.regex' => 'Enter a valid blood type such as O+, A-, or AB+.',
            'purok_id.integer' => 'Please select a purok from the list.',
            'purok_id.exists' => 'The selected purok is no longer available. Please choose another.',
            'household_id.integer' => 'Please select a household from the list.',
            'household_id.exists' => 'The selected household is no longer available. Please choose another.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateResidentFields($residentId = null): array
    {
        // Fixed residency options for new input. A record saved before the
        // select existed may hold a legacy free-text value; accept that one
        // stored value on update so old rows remain editable without a
        // migration, while every new/changed value must match the list.
        $allowedResidency = ['Permanent', 'Temporary', 'Transient'];
        if ($residentId) {
            $legacy = Resident::withTrashed()->whereKey($residentId)->value('residency_status');
            if (is_string($legacy) && $legacy !== '' && ! in_array($legacy, $allowedResidency, true)) {
                $allowedResidency[] = $legacy;
            }
        }

        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:10',
            'birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'birthplace' => 'nullable|string|max:150',
            'sex' => 'required|in:Male,Female,Other',
            'civil_status' => 'required|in:Single,Married,Divorced,Widowed,Separated',
            'nationality' => 'nullable|string|max:50',
            'religion' => 'nullable|string|max:100',
            'education_level' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
            'spouse_name' => 'nullable|string|max:100',
            'blood_type' => ['nullable', 'string', 'max:5', 'regex:/^(?:A|B|AB|O)[+-]?$/i'],
            'phone_number' => ['nullable', 'string', 'max:15', 'regex:/^(?=(?:.*\d){7,})\+?[0-9()\-\s]+$/'],
            'email' => ['nullable', 'string', 'email', 'max:150', Rule::unique('residents', 'email')->ignore($residentId)->whereNull('deleted_at')],
            'address' => 'nullable|string|max:255',
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'residency_status' => ['nullable', 'string', 'max:50', Rule::in($allowedResidency)],
            'voter_status' => 'nullable|boolean',
            'is_household_head' => 'nullable|boolean',
            'purok_id' => 'nullable|integer|exists:puroks,id',
            'household_id' => [
                'nullable',
                'integer',
                Rule::exists('households', 'id')
                    ->whereNull('deleted_at')
                    ->whereIn('status', ['Occupied', 'Under Construction']),
            ],
            'status' => 'required|in:Active,Archived',
        ];
    }
}
