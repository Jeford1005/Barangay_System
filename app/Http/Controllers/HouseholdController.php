<?php

namespace App\Http\Controllers;

use App\Models\Concerns\Searchable;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Services\HouseholdResidentSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseholdController extends Controller
{
    public function index(Request $request)
    {
        $query = Household::with(['purok', 'head']);

        // Search - sanitized input. LIKE wildcards in the input are escaped
        // so `%` and `_` only ever match literally.
        if ($request->filled('search')) {
            $search = Searchable::normalizeSearchTerm(mb_substr(strip_tags((string) $request->search), 0, 100)) ?? '';
            $like = '%'.self::escapeLike($search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("household_code LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("street LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("barangay LIKE ? ESCAPE '\\'", [$like]);
            });
        }

        // Filter by purok
        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        $households = $query->orderBy('household_code')->paginate(20)->withQueryString();

        $puroks = Purok::pluck('name', 'id');

        $page = max(1, (int) $request->input('page', 1));

        return view('household.index', compact('households', 'puroks'))
            ->with('i', ($page - 1) * $households->perPage());
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
        abort_unless(Auth::user()?->hasPermission('households.manage'), 403);

        $puroks = Purok::pluck('name', 'id');
        // Bounded dropdown options: the full resident table must never be
        // loaded into a select list.
        $residents = Resident::active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(1000)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']);

        return view('household.create', compact('puroks', 'residents'));
    }

    public function store(Request $request, HouseholdResidentSync $householdResidentSync)
    {
        abort_unless(Auth::user()?->hasPermission('households.manage'), 403);

        $validated = $this->validateHousehold($request);
        $validated['created_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $household = Household::create($validated);
            $householdResidentSync->syncHouseholdHead($household, $validated['head_of_household_id'] ?? null);
            DB::commit();
            Cache::forget('auth.household-options');

            AuditLog::record(
                'household.created',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['household_id' => $household->id, 'household_code' => $household->household_code],
            );

            return redirect()->route('households.index')
                ->with('success', 'Household created successfully.');
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to create household.'])->withInput();
        }
    }

    public function edit(Household $household)
    {
        abort_unless(Auth::user()?->hasPermission('households.manage'), 403);

        $puroks = Purok::pluck('name', 'id');
        // Bounded dropdown options: the full resident table must never be
        // loaded into a select list.
        $residents = Resident::active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(1000)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']);

        return view('household.edit', compact('household', 'puroks', 'residents'));
    }

    public function update(Request $request, Household $household, HouseholdResidentSync $householdResidentSync)
    {
        abort_unless(Auth::user()?->hasPermission('households.manage'), 403);

        $validated = $this->validateHousehold($request, $household->id);
        $validated['updated_by'] = Auth::id();

        $previousHeadId = $household->head_of_household_id ? (int) $household->head_of_household_id : null;
        $previousHeadName = $previousHeadId
            ? (string) (Resident::whereKey($previousHeadId)->first()?->full_name ?? '')
            : '';
        $newHeadId = array_key_exists('head_of_household_id', $validated) && $validated['head_of_household_id'] !== null
            ? (int) $validated['head_of_household_id']
            : null;

        DB::beginTransaction();
        try {
            $household->update($validated);
            $householdResidentSync->syncHouseholdHead($household, $validated['head_of_household_id'] ?? null);
            // The sync layer clears/promotes head flags with direct queries;
            // refresh so the response reflects the row as stored.
            $household->refresh();
            DB::commit();
            Cache::forget('auth.household-options');

            AuditLog::record(
                'household.updated',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['household_id' => $household->id, 'household_code' => $household->household_code],
            );

            // A head change is never silent: name who was demoted and who
            // now leads the household.
            $message = 'Household updated successfully.';
            if ($previousHeadId && $newHeadId !== $previousHeadId) {
                $incoming = $newHeadId
                    ? (string) (Resident::whereKey($newHeadId)->first()?->full_name ?? 'none')
                    : 'none';
                $outgoing = trim($previousHeadName) !== '' ? trim($previousHeadName) : 'The previous head';
                $message = "Household updated successfully. {$outgoing} is no longer the head; {$incoming} is now the head of household.";
            } elseif (! $previousHeadId && $newHeadId) {
                $incoming = (string) (Resident::whereKey($newHeadId)->first()?->full_name ?? 'the selected resident');
                $message = "Household updated successfully. {$incoming} is now the head of household.";
            }

            return redirect()->route('households.index')
                ->with('success', $message);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to update household.'])->withInput();
        }
    }

    public function destroy(Request $request, Household $household)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $householdId = $household->getKey();
        $householdCode = $household->household_code;

        DB::beginTransaction();
        try {
            $household->delete();
            DB::commit();
            Cache::forget('auth.household-options');

            AuditLog::record(
                'household.deleted',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['household_id' => $householdId, 'household_code' => $householdCode],
            );

            return redirect()->route('households.index')
                ->with('success', 'Household deleted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to delete household.']);
        }
    }

    private function validateHousehold(Request $request, $householdId = null)
    {
        $rules = [
            'household_code' => 'required|string|max:20|unique:households,household_code',
            'purok_id' => 'nullable|integer|min:1|max:4294967295|exists:puroks,id',
            'sitio' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:150',
            'barangay' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:50',
            'zip_code' => ['nullable', 'string', 'max:10', 'regex:/^[0-9]{4,10}$/'],
            'house_type' => 'required|in:Single,Duplex,Apartment,Townhouse,Other',
            // Lot/floor areas stay free-text: values carry units (e.g. "100 sqm").
            // The pattern keeps that while rejecting anything that is not a
            // number with an optional area unit.
            'lot_area' => ['nullable', 'string', 'max:50', 'regex:/^\d{1,6}(\.\d{1,2})?\s?(sq\.?\s?m\.?|sqm|m2|m²|sq\.?\s?ft\.?|sqft|ft2|ha|hectares?)?$/i'],
            'floor_area' => ['nullable', 'string', 'max:50', 'regex:/^\d{1,6}(\.\d{1,2})?\s?(sq\.?\s?m\.?|sqm|m2|m²|sq\.?\s?ft\.?|sqft|ft2|ha|hectares?)?$/i'],
            'year_built' => 'nullable|integer|min:1800|max:'.date('Y'),
            'ownership' => 'required|in:Owned,Rented,Leased,Occupied',
            // Capped well below the unsigned-int ceiling so a crafted value
            // cannot inflate member counts toward 4B.
            'num_members' => 'required|integer|min:1|max:50',
            'head_of_household_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::exists('residents', 'id')->where('status', 'Active')],
            'status' => 'required|in:Occupied,Vacant,Under Construction',
            'remarks' => 'nullable|string|max:1000',
        ];

        if ($householdId) {
            $rules['household_code'] = 'required|string|max:20|unique:households,household_code,'.$householdId;
        }

        return $request->validate($rules, [
            'zip_code.regex' => 'The ZIP code must contain digits only.',
            'lot_area.regex' => 'Enter the lot area as a number with an optional unit, e.g. "100 sqm".',
            'floor_area.regex' => 'Enter the floor area as a number with an optional unit, e.g. "50 sqm".',
            'purok_id.integer' => 'Please select a purok from the list, or leave it unassigned.',
            'purok_id.exists' => 'The selected purok is no longer available. Please choose another.',
            'head_of_household_id.integer' => 'Please select a head of household from the list.',
            'head_of_household_id.exists' => 'The selected resident is no longer available. Please choose another.',
        ]);
    }
}
