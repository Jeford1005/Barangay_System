<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResidentFormRequest;
use App\Http\Requests\StoreResidentRequest;
use App\Http\Requests\UpdateResidentRequest;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Resident registry: browsing and search for every office user, record
 * management for holders of `residents.manage`, archive/restore for admins.
 *
 * `full_name` and `age` are recomputed by a saving hook on the model — this
 * controller never writes them.
 */
class ResidentController extends Controller
{
    /** Reason written to the linked account whenever a resident is archived. */
    public const ARCHIVE_SUSPENSION_REASON = 'Resident record archived by the barangay office.';

    /* ------------------------------------------------------------------ */
    /* Browsing                                                            */
    /* ------------------------------------------------------------------ */

    /** Filterable, paginated resident list. */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'purok_id' => ['nullable', 'integer', 'exists:puroks,id'],
            'status' => ['nullable', 'string', 'in:Active,Archived,All'],
        ]);

        $search = $filters['search'] ?? null;
        $purokId = isset($filters['purok_id']) && $filters['purok_id'] !== null ? (int) $filters['purok_id'] : null;
        $status = $filters['status'] ?? Resident::STATUS_ACTIVE;

        $residents = Resident::query()
            ->with(['purok', 'household'])
            ->search($search)
            ->when($purokId !== null, fn ($query) => $query->where('purok_id', $purokId))
            ->when($status !== 'All', fn ($query) => $query->where('status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return view('residents.index', [
            'residents' => $residents,
            'puroks' => Purok::orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'purok_id' => $purokId,
                'status' => $status,
            ],
            'activeCount' => Resident::active()->count(),
            'archivedCount' => Resident::archived()->count(),
        ]);
    }

    /** Printable directory of active residents, grouped by purok. */
    public function directory(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $term = $filters['search'] ?? null;

        $residents = Resident::query()->search($term)->forDirectory()->with('household')->get();

        $byPurok = [];
        $unassigned = [];

        foreach ($residents as $resident) {
            if ($resident->purok_id === null) {
                $unassigned[] = $resident;
            } else {
                $byPurok[$resident->purok_id][] = $resident;
            }
        }

        $groups = [];

        foreach (Purok::orderBy('name')->get() as $purok) {
            $rows = $byPurok[$purok->id] ?? [];

            // While searching, only purok sections with matches are worth printing.
            if ($rows === [] && filled($term)) {
                continue;
            }

            $groups[] = ['purok' => $purok, 'residents' => $rows];
        }

        if ($unassigned !== []) {
            $groups[] = ['purok' => null, 'residents' => $unassigned];
        }

        return view('residents.directory', [
            'groups' => $groups,
            'total' => count($residents),
            'search' => $term,
        ]);
    }

    /** Resident profile. */
    public function show(Resident $resident): View
    {
        return view('residents.show', [
            'resident' => $resident->load(['purok', 'household']),
            'account' => User::where('resident_id', $resident->id)->first(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Create / update                                                     */
    /* ------------------------------------------------------------------ */

    public function create(): View
    {
        $resident = new Resident();

        return view('residents.create', [
            'resident' => $resident,
            'puroks' => Purok::orderBy('name')->get(),
            'households' => $this->householdChoices($resident),
        ]);
    }

    public function store(StoreResidentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isAdmin = $request->user()->isAdmin();

        // Staff and agents can never file a resident as Archived.
        $status = $isAdmin && isset($data['status'])
            ? $data['status']
            : Resident::STATUS_ACTIVE;

        unset($data['status'], $data['photo'], $data['remove_photo']);

        $resident = DB::transaction(function () use ($request, $data, $status): Resident {
            $resident = new Resident();
            $resident->fill($data);
            $resident->status = $status;
            $resident->photo_path = $this->storePhoto($request, null);
            $resident->save();
            $resident->refresh(); // audit against the stored (DB) representation

            $this->syncHouseholdCounts(null, $resident->household_id);

            AuditLog::record(
                'created',
                'resident',
                $resident->id,
                null,
                Arr::only($resident->getRawOriginal(), array_merge(array_keys($data), ['status', 'photo_path']))
            );

            return $resident;
        });

        return redirect()
            ->route('residents.show', $resident)
            ->with('status', "{$resident->full_name} was added to the resident registry.");
    }

    public function edit(Resident $resident): View
    {
        $resident->load(['purok', 'household']);

        return view('residents.edit', [
            'resident' => $resident,
            'puroks' => Purok::orderBy('name')->get(),
            'households' => $this->householdChoices($resident),
        ]);
    }

    public function update(UpdateResidentRequest $request, Resident $resident): RedirectResponse
    {
        $data = $request->validated();
        $isAdmin = $request->user()->isAdmin();

        // Only administrators may change the lifecycle status; anyone else keeps
        // whatever the record already has, whatever was posted.
        $status = $isAdmin && isset($data['status'])
            ? $data['status']
            : $resident->status;

        unset($data['status'], $data['photo'], $data['remove_photo']);

        $beforeRaw = $resident->getRawOriginal();
        $oldHouseholdId = $resident->household_id;

        DB::transaction(function () use ($request, $resident, $data, $status, $beforeRaw, $oldHouseholdId): void {
            $resident->fill($data);
            $resident->status = $status;
            $resident->photo_path = $this->storePhoto($request, $resident->photo_path);
            $resident->save();
            $resident->refresh(); // audit against the stored (DB) representation

            $this->syncHouseholdCounts($oldHouseholdId, $resident->household_id);

            $afterRaw = $resident->getRawOriginal();
            $before = [];
            $after = [];

            foreach (array_keys($data) as $key) {
                $old = $beforeRaw[$key] ?? null;
                $new = $afterRaw[$key] ?? null;

                if ((string) $old !== (string) $new) {
                    $before[$key] = $old;
                    $after[$key] = $new;
                }
            }

            foreach (['status', 'photo_path'] as $key) {
                $old = $beforeRaw[$key] ?? null;
                $new = $afterRaw[$key] ?? null;

                if ((string) $old !== (string) $new) {
                    $before[$key] = $old;
                    $after[$key] = $new;
                }
            }

            AuditLog::record('updated', 'resident', $resident->id, $before ?: null, $after ?: null);
        });

        return redirect()
            ->route('residents.show', $resident)
            ->with('status', "{$resident->full_name} was updated.");
    }

    /* ------------------------------------------------------------------ */
    /* Archive / restore (administrator only)                              */
    /* ------------------------------------------------------------------ */

    public function archive(Request $request, Resident $resident): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($resident->isArchived()) {
            return redirect()->back()->with('error', "{$resident->full_name} is already archived.");
        }

        $suspended = [];

        DB::transaction(function () use ($resident, &$suspended): void {
            $resident->forceFill(['status' => Resident::STATUS_ARCHIVED])->save();

            foreach (User::where('resident_id', $resident->id)->get() as $user) {
                $user->forceFill([
                    'status' => User::STATUS_SUSPENDED,
                    'suspended_at' => now(),
                    'suspension_reason' => self::ARCHIVE_SUSPENSION_REASON,
                ])->save();

                $suspended[] = $user->email;
            }
        });

        $after = ['status' => Resident::STATUS_ARCHIVED];

        if ($suspended !== []) {
            $after['suspended_accounts'] = $suspended;
        }

        AuditLog::record('archived', 'resident', $resident->id, ['status' => Resident::STATUS_ACTIVE], $after);

        $message = "{$resident->full_name} archived.";

        if ($suspended !== []) {
            $message .= ' The linked portal account was suspended.';
        }

        return redirect()->back()->with('status', $message);
    }

    public function restore(Request $request, Resident $resident): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if (! $resident->isArchived()) {
            return redirect()->back()->with('error', "{$resident->full_name} is already active.");
        }

        $reinstated = [];

        DB::transaction(function () use ($resident, &$reinstated): void {
            $resident->forceFill(['status' => Resident::STATUS_ACTIVE])->save();

            foreach (User::where('resident_id', $resident->id)->get() as $user) {
                if ($user->status !== User::STATUS_SUSPENDED || $user->suspension_reason !== self::ARCHIVE_SUSPENSION_REASON) {
                    continue;
                }

                // Accounts suspended for some other reason are left untouched;
                // an account that was never approved goes back to pending.
                $user->forceFill([
                    'status' => $user->approved_at !== null ? User::STATUS_ACTIVE : User::STATUS_PENDING,
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ])->save();

                $reinstated[] = $user->email;
            }
        });

        $after = ['status' => Resident::STATUS_ACTIVE];

        if ($reinstated !== []) {
            $after['reinstated_accounts'] = $reinstated;
        }

        AuditLog::record('restored', 'resident', $resident->id, ['status' => Resident::STATUS_ARCHIVED], $after);

        $message = "{$resident->full_name} restored to the active registry.";

        if ($reinstated !== []) {
            $message .= ' The linked portal account is active again.';
        }

        return redirect()->back()->with('status', $message);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Households a resident may be assigned to, always including the record's
     * current household so an existing assignment can be kept on edit.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Household>
     */
    private function householdChoices(Resident $resident)
    {
        return Household::query()
            ->where(function ($query) use ($resident): void {
                $query->whereIn('status', ResidentFormRequest::ASSIGNABLE_HOUSEHOLD_STATUSES);

                if ($resident->household_id !== null) {
                    $query->orWhere('id', $resident->household_id);
                }
            })
            ->orderBy('household_number')
            ->get();
    }

    /**
     * Store (or clear) the photo on the private `local` disk and return the
     * value that should end up in photo_path.
     */
    private function storePhoto(Request $request, ?string $currentPath): ?string
    {
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
            $filename = now()->format('YmdHis').'-'.Str::random(12).'.'.$extension;

            $path = Storage::disk('local')->putFileAs('residents', $file, $filename);

            if ($path === false) {
                return $currentPath;
            }

            if ($path !== $currentPath) {
                $this->forgetPhoto($currentPath);
            }

            return $path;
        }

        if ($request->boolean('remove_photo')) {
            $this->forgetPhoto($currentPath);

            return null;
        }

        return $currentPath;
    }

    /** Delete a stored photo from whichever disk holds it. */
    private function forgetPhoto(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);

                return;
            }
        }
    }

    /** Keep household member counts in step when a resident moves in or out. */
    private function syncHouseholdCounts(mixed $oldId, mixed $newId): void
    {
        $ids = array_unique(array_filter([
            $oldId !== null ? (int) $oldId : null,
            $newId !== null ? (int) $newId : null,
        ]));

        foreach ($ids as $id) {
            Household::query()->whereKey($id)->first()?->syncMemberCount();
        }
    }
}
