<?php

namespace App\Http\Controllers;

use App\Models\Concerns\Searchable;
use App\Models\Purok;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurokController extends Controller
{
    public function index(Request $request)
    {
        $query = Purok::query();

        if ($request->filled('search')) {
            $search = Searchable::normalizeSearchTerm(mb_substr(strip_tags((string) $request->search), 0, 100)) ?? '';
            $like = '%'.self::escapeLike($search).'%';
            // Grouped so the OR never leaks past additional filters.
            $query->where(function ($q) use ($like) {
                $q->whereRaw("name LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("code LIKE ? ESCAPE '\\'", [$like]);
            });
        }

        $puroks = $query->orderBy('name')->paginate(20)->withQueryString();

        $page = max(1, (int) $request->input('page', 1));

        return view('purok.index', compact('puroks'))
            ->with('i', ($page - 1) * $puroks->perPage());
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

            AuditLog::record(
                'purok.created',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['purok_id' => $purok->id, 'name' => $purok->name, 'code' => $purok->code],
            );

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

        // The puroks table carries no updated_by column yet; record the actor
        // only where the column exists so the write never breaks.
        if (Schema::hasColumn('puroks', 'updated_by')) {
            $validated['updated_by'] = Auth::id();
        }

        DB::beginTransaction();
        try {
            // Re-read under a row lock so a concurrent delete cannot slip
            // between routing and the write.
            $locked = Purok::whereKey($purok->getKey())->lockForUpdate()->firstOrFail();
            $locked->update($validated);
            DB::commit();
            Cache::forget('auth.purok-options');

            AuditLog::record(
                'purok.updated',
                Auth::id(),
                Auth::user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['purok_id' => $locked->id, 'name' => $locked->name, 'code' => $locked->code],
            );

            return redirect()->route('puroks.index')
                ->with('success', 'Purok updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to update purok.'])->withInput();
        }
    }

    public function destroy(Request $request, Purok $purok)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $purokId = $purok->getKey();
        $purokName = $purok->name;
        $purokCode = $purok->code;

        // The occupancy check and the delete run in one transaction with the
        // row locked and the check repeated inside: a resident or household
        // assigned between a pre-check and the delete must still block it.
        $blocked = DB::transaction(function () use ($purok) {
            $locked = Purok::whereKey($purok->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->residents()->exists() || $locked->households()->exists()) {
                return true;
            }

            $locked->delete();

            return false;
        });

        if ($blocked) {
            return redirect()->route('puroks.index')
                ->with('error', 'Cannot delete purok that has associated residents or households.');
        }

        Cache::forget('auth.purok-options');

        AuditLog::record(
            'purok.deleted',
            Auth::id(),
            Auth::user()?->email,
            $request->ip(),
            $request->userAgent(),
            ['purok_id' => $purokId, 'name' => $purokName, 'code' => $purokCode],
        );

        return redirect()->route('puroks.index')
            ->with('success', 'Purok deleted successfully.');
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

    private function validatePurok(Request $request, $purokId = null)
    {
        $rules = [
            'name' => 'required|string|max:50|unique:puroks,name',
            'code' => 'nullable|string|max:10|unique:puroks,code',
        ];

        if ($purokId) {
            $rules['name'] = 'required|string|max:50|unique:puroks,name,'.$purokId;
            $rules['code'] = 'nullable|string|max:10|unique:puroks,code,'.$purokId;
        }

        $validated = $request->validate($rules);

        // An empty-string code is "no code": normalize to NULL so blank
        // submissions share one representation and never collide on the
        // unique index.
        if (array_key_exists('code', $validated) && trim((string) $validated['code']) === '') {
            $validated['code'] = null;
        }

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim((string) $validated['name']);
        }

        return $validated;
    }
}
