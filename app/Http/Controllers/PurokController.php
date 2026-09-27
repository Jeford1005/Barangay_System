<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurokRequest;
use App\Http\Requests\UpdatePurokRequest;
use App\Models\AuditLog;
use App\Models\Purok;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Purok (zone) registry.
 *
 * Browsing is open to every office user (`puroks.view`); every write action
 * repeats the administrator check inline on top of the route middleware.
 */
class PurokController extends Controller
{
    /** Purok list with resident / household counts. */
    public function index(): View
    {
        $puroks = Purok::query()
            ->withCount(['residents', 'households'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('puroks.index', ['puroks' => $puroks]);
    }

    /** Standalone "new purok" page (the same form also lives in a dialog on the index). */
    public function create(): View
    {
        return view('puroks.create', ['purok' => new Purok()]);
    }

    public function store(StorePurokRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'code', 'description']);

        // The code column is NOT NULL: fall back to the next free P# code.
        if (blank($data['code'] ?? null)) {
            $data['code'] = $this->nextAvailableCode();
        }

        $purok = Purok::create($data);

        AuditLog::record('created', 'purok', $purok->id, null, $purok->only(['name', 'code', 'description']));

        return redirect()
            ->route('puroks.index')
            ->with('status', "Purok “{$purok->name}” ({$purok->code}) created.");
    }

    public function edit(Purok $purok): View
    {
        return view('puroks.edit', ['purok' => $purok]);
    }

    public function update(UpdatePurokRequest $request, Purok $purok): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'code', 'description']);

        // A blank code means "leave the current code alone" (column is NOT NULL).
        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        }

        $before = [];
        $after = [];

        foreach ($data as $key => $value) {
            $current = $purok->getAttribute($key);

            if ((string) $current !== (string) $value) {
                $before[$key] = $current;
                $after[$key] = $value;
            }
        }

        $purok->fill($data)->save();

        AuditLog::record('updated', 'purok', $purok->id, $before ?: null, $after ?: null);

        return redirect()
            ->route('puroks.index')
            ->with('status', "Purok “{$purok->name}” updated.");
    }

    /** Deleting is refused while the purok still owns residents or households. */
    public function destroy(Request $request, Purok $purok): RedirectResponse
    {
        if (! ($request->user()?->isAdmin() ?? false)) {
            abort(403, 'Only administrators can delete puroks.');
        }

        $residents = $purok->residents()->count();
        $households = $purok->households()->count();

        if ($residents > 0 || $households > 0) {
            return redirect()->back()->with(
                'error',
                "“{$purok->name}” cannot be deleted yet — it still has {$residents} resident(s) and {$households} household(s). Move or archive them first."
            );
        }

        $snapshot = $purok->only(['name', 'code', 'description']);

        $purok->delete();

        AuditLog::record('deleted', 'purok', $purok->id, $snapshot, null);

        return redirect()
            ->route('puroks.index')
            ->with('status', "Purok “{$snapshot['name']}” deleted.");
    }

    /** Next free "P1", "P2" … code so a blank code never breaks the NOT NULL column. */
    private function nextAvailableCode(): string
    {
        $highest = Purok::query()
            ->pluck('code')
            ->filter(fn ($code): bool => (bool) preg_match('/^P(\d+)$/', (string) $code))
            ->map(fn ($code): int => (int) preg_replace('/\D+/', '', (string) $code))
            ->max();

        $number = (int) $highest;

        do {
            $number++;
            $code = 'P'.$number;
        } while (Purok::where('code', $code)->exists());

        return $code;
    }
}
