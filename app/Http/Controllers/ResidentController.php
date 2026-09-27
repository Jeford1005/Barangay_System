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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResidentController extends Controller
{
    public function index(Request $request)
    {
        $query = Resident::with(['purok', 'household']);

        // Search - using parameterized queries via Eloquent
        if ($request->filled('search')) {
            $search = mb_substr(strip_tags((string) $request->search), 0, 100);
            $fullNameSql = $query->getConnection()->getDriverName() === 'sqlite'
                ? "first_name || ' ' || last_name"
                : 'CONCAT(first_name, " ", last_name)';
            $query->where(function ($q) use ($search, $fullNameSql) {
                $q->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw($fullNameSql.' LIKE ?', ['%'.$search.'%']);
            });
        }

        // Filter by purok
        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        // Filter by household
        if ($request->filled('household_id')) {
            $query->where('household_id', $request->integer('household_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $residents = $query->orderBy('last_name')->paginate(20);

        $puroks = Purok::pluck('name', 'id');
        $households = Household::pluck('household_code', 'id');

        return view('resident.index', compact('residents', 'puroks', 'households'))
            ->with('i', ($request->input('page', 1) - 1) * $residents->perPage());
    }

    /**
     * Printable resident directory grouped by purok with per-purok counts.
     * Standalone document layout (no app chrome), active residents only,
     * purok-less residents listed under their own heading so nobody is
     * silently dropped from the record.
     */
    public function directory()
    {
        $residents = Resident::active()
            ->with('purok')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $grouped = $residents
            ->groupBy(fn ($r) => $r->purok?->name ?? 'No Purok Assigned')
            ->sortBy(fn ($rows, $key) => $key === 'No Purok Assigned' ? PHP_INT_MAX : $key);

        $purokCounts = $grouped->map(fn ($rows) => $rows->count());

        return view('residents.directory', [
            'grouped' => $grouped,
            'purokCounts' => $purokCounts,
            'total' => $residents->count(),
        ]);
    }

    public function create()
    {
        $puroks = Purok::pluck('name', 'id');
        $households = Household::pluck('household_code', 'id');

        return view('resident.create', compact('puroks', 'households'));
    }

    public function store(Request $request, HouseholdResidentSync $householdResidentSync)
    {
        $validated = $this->validateResident($request);

        // The database stores nationality as NOT NULL with a Filipino default;
        // normalize a cleared optional field before Eloquent persists it.
        $validated['nationality'] = ($validated['nationality'] ?? null) ?: 'Filipino';
        if (Auth::user()?->isStaff()) {
            $validated['status'] = 'Active';
        }
        $validated = $this->preparePhoto($validated, $request);
        $validated['created_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $resident = Resident::create($validated);
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
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to register resident.'])->withInput();
        }
    }

    public function edit(Resident $resident)
    {
        $puroks = Purok::pluck('name', 'id');
        $households = Household::pluck('household_code', 'id');

        return view('resident.edit', compact('resident', 'puroks', 'households'));
    }

    public function update(Request $request, Resident $resident, HouseholdResidentSync $householdResidentSync)
    {
        $previousHouseholdId = $resident->household_id;
        $before = Arr::only($resident->getAttributes(), array_keys($this->validateResidentFields()));
        $validated = $this->validateResident($request, $resident->id);
        $validated['nationality'] = ($validated['nationality'] ?? null) ?: 'Filipino';
        $validated = $this->preparePhoto($validated, $request);

        // Status changes are an administrator-only lifecycle action. Staff
        // may correct ordinary profile fields without archiving or restoring.
        if (Auth::user()?->isStaff()) {
            $validated['status'] = $resident->status;
        }

        $validated['updated_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $resident->update($validated);
            $householdResidentSync->syncResidentAfterSave($resident, $previousHouseholdId);

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

            return redirect()->route('residents.index')
                ->with('success', 'Resident information updated successfully.');
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

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

    private function preparePhoto(array $validated, Request $request): array
    {
        if ($request->hasFile('photo')) {
            // Private disk, not `public`: photos are personal data and are
            // streamed through ResidentPhotoController, which checks the
            // viewer's role or ownership.
            $validated['photo'] = $request->file('photo')->store('residents', 'local');
        } else {
            unset($validated['photo']);
        }

        return $validated;
    }

    private function validateResident(Request $request, $residentId = null)
    {
        return $request->validate($this->validateResidentFields(), [
            'phone_number.regex' => 'The phone number must contain at least one digit. Spaces, +, -, and parentheses are allowed.',
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
    private function validateResidentFields(): array
    {
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
            'phone_number' => ['nullable', 'string', 'max:15', 'regex:/^(?=.*\d)\+?[0-9()\-\s]+$/'],
            'email' => 'nullable|string|email|max:150',
            'address' => 'nullable|string|max:255',
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'residency_status' => 'nullable|string|max:50',
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
