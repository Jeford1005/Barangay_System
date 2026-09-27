<?php

namespace App\Http\Controllers;

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

        // Search - sanitized input
        if ($request->filled('search')) {
            $search = mb_substr(strip_tags((string) $request->search), 0, 100);
            $query->where(function ($q) use ($search) {
                $q->where('household_code', 'like', '%'.$search.'%')
                    ->orWhere('street', 'like', '%'.$search.'%')
                    ->orWhere('barangay', 'like', '%'.$search.'%');
            });
        }

        // Filter by purok
        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        $households = $query->orderBy('household_code')->paginate(20);

        $puroks = Purok::pluck('name', 'id');

        return view('household.index', compact('households', 'puroks'))
            ->with('i', ($request->input('page', 1) - 1) * $households->perPage());
    }

    public function create()
    {
        $puroks = Purok::pluck('name', 'id');
        $residents = Resident::active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']);

        return view('household.create', compact('puroks', 'residents'));
    }

    public function store(Request $request, HouseholdResidentSync $householdResidentSync)
    {
        $validated = $this->validateHousehold($request);
        $validated['created_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $household = Household::create($validated);
            $householdResidentSync->syncHouseholdHead($household, $validated['head_of_household_id'] ?? null);
            DB::commit();
            Cache::forget('auth.household-options');

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
        $puroks = Purok::pluck('name', 'id');
        $residents = Resident::active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']);

        return view('household.edit', compact('household', 'puroks', 'residents'));
    }

    public function update(Request $request, Household $household, HouseholdResidentSync $householdResidentSync)
    {
        $validated = $this->validateHousehold($request, $household->id);
        $validated['updated_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $household->update($validated);
            $householdResidentSync->syncHouseholdHead($household, $validated['head_of_household_id'] ?? null);
            DB::commit();
            Cache::forget('auth.household-options');

            return redirect()->route('households.index')
                ->with('success', 'Household updated successfully.');
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to update household.'])->withInput();
        }
    }

    public function destroy(Household $household)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        DB::beginTransaction();
        try {
            $household->delete();
            DB::commit();
            Cache::forget('auth.household-options');

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
            'purok_id' => 'nullable|integer|exists:puroks,id',
            'sitio' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:150',
            'barangay' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:50',
            'zip_code' => ['nullable', 'string', 'max:10', 'regex:/^[0-9]{4,10}$/'],
            'house_type' => 'required|in:Single,Duplex,Apartment,Townhouse,Other',
            'lot_area' => 'nullable|string|max:50',
            'floor_area' => 'nullable|string|max:50',
            'year_built' => 'nullable|integer|min:1800|max:'.date('Y'),
            'ownership' => 'required|in:Owned,Rented,Leased,Occupied',
            'num_members' => 'required|integer|min:1|max:4294967295',
            'head_of_household_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->where('status', 'Active')],
            'status' => 'required|in:Occupied,Vacant,Under Construction',
            'remarks' => 'nullable|string|max:1000',
        ];

        if ($householdId) {
            $rules['household_code'] = 'required|string|max:20|unique:households,household_code,'.$householdId;
        }

        return $request->validate($rules, [
            'zip_code.regex' => 'The ZIP code must contain digits only.',
            'purok_id.integer' => 'Please select a purok from the list, or leave it unassigned.',
            'purok_id.exists' => 'The selected purok is no longer available. Please choose another.',
            'head_of_household_id.integer' => 'Please select a head of household from the list.',
            'head_of_household_id.exists' => 'The selected resident is no longer available. Please choose another.',
        ]);
    }
}
