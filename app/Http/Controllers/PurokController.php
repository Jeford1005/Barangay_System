<?php

namespace App\Http\Controllers;

use App\Models\Purok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PurokController extends Controller
{
    public function index(Request $request)
    {
        $query = Purok::query();

        if ($request->filled('search')) {
            $search = mb_substr(strip_tags((string) $request->search), 0, 100);
            $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('code', 'like', '%'.$search.'%');
        }

        $puroks = $query->orderBy('name')->paginate(20);

        return view('purok.index', compact('puroks'))
            ->with('i', ($request->input('page', 1) - 1) * $puroks->perPage());
    }

    public function create()
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        return view('purok.create');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $validated = $this->validatePurok($request);
        $validated['created_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $purok = Purok::create($validated);
            DB::commit();
            Cache::forget('auth.purok-options');

            return redirect()->route('puroks.index')
                ->with('success', 'Purok created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to create purok.'])->withInput();
        }
    }

    public function edit(Purok $purok)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        return view('purok.edit', compact('purok'));
    }

    public function update(Request $request, Purok $purok)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $validated = $this->validatePurok($request, $purok->id);

        DB::beginTransaction();
        try {
            $purok->update($validated);
            DB::commit();
            Cache::forget('auth.purok-options');

            return redirect()->route('puroks.index')
                ->with('success', 'Purok updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to update purok.'])->withInput();
        }
    }

    public function destroy(Purok $purok)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $hasResidents = $purok->residents()->exists();
        $hasHouseholds = $purok->households()->exists();

        if ($hasResidents || $hasHouseholds) {
            return redirect()->route('puroks.index')
                ->with('error', 'Cannot delete purok that has associated residents or households.');
        }

        DB::beginTransaction();
        try {
            $purok->delete();
            DB::commit();
            Cache::forget('auth.purok-options');

            return redirect()->route('puroks.index')
                ->with('success', 'Purok deleted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to delete purok.']);
        }
    }

    private function validatePurok(Request $request, $purokId = null)
    {
        $rules = [
            'name' => 'required|string|max:50|unique:puroks,name',
            'code' => 'nullable|string|max:10',
        ];

        if ($purokId) {
            $rules['name'] = 'required|string|max:50|unique:puroks,name,'.$purokId;
        }

        return $request->validate($rules);
    }
}
