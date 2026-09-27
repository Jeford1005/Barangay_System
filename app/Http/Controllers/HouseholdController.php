<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Requests\UpdateHouseholdRequest;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Households registry: families of Barangay Bidduang keyed by an auto
 * generated "HH-###" number. Members and the member count live on the
 * residents table — the household only mirrors the count.
 */
class HouseholdController extends Controller
{
    /** @var list<string> */
    private const TRACKED = [
        'household_number', 'address', 'purok_id', 'head_resident_id',
        'house_type', 'ownership', 'status',
    ];

    /* ------------------------------------------------------------------ */
    /* Listing                                                             */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $purokId = $this->purokFilter($request->query('purok_id'));

        $rows = Household::query()
            ->with(['purok', 'head'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('household_number', 'like', $like)
                        ->orWhere('address', 'like', $like);
                });
            })
            ->when($purokId !== null, fn (Builder $query): Builder => $query->where('purok_id', $purokId))
            ->orderBy('household_number')
            ->paginate(20)
            ->withQueryString();

        return view('households.index', [
            'rows' => $rows,
            'search' => $search,
            'purokId' => $purokId,
            'puroks' => Purok::orderBy('code')->get(),
        ]);
    }

    public function show(Household $household): View
    {
        return view('households.show', [
            'household' => $household->load(['purok', 'head']),
            'members' => $household->members()
                ->with('purok')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Create / update                                                     */
    /* ------------------------------------------------------------------ */

    public function create(): View
    {
        return view('households.create', [
            'household' => null,
            'nextNumber' => Household::nextNumber(),
            'puroks' => Purok::orderBy('code')->get(),
            'heads' => $this->headOptions(null),
        ]);
    }

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $headId = $data['head_resident_id'] ?? null;

        $household = DB::transaction(function () use ($data, $headId): Household {
            $household = Household::create([
                'household_number' => $data['household_number'],
                'address' => $data['address'],
                'purok_id' => $data['purok_id'] ?? null,
                'head_resident_id' => $headId,
                'house_type' => $data['house_type'],
                'ownership' => $data['ownership'],
                'status' => $data['status'],
                'member_count' => 0,
            ]);

            if ($headId !== null) {
                // The head is (by definition) a member of this household.
                Resident::findOrFail($headId)
                    ->forceFill(['household_id' => $household->id])
                    ->save();
            }

            $household->syncMemberCount();

            return $household;
        });

        AuditLog::record('created', 'household', $household->id, null, $household->only(self::TRACKED) + [
            'member_count' => $household->member_count,
        ]);

        return redirect()
            ->route('households.show', $household)
            ->with('status', "Household {$household->household_number} created.");
    }

    public function edit(Household $household): View
    {
        return view('households.edit', [
            'household' => $household->load('head'),
            'puroks' => Purok::orderBy('code')->get(),
            'heads' => $this->headOptions($household),
        ]);
    }

    public function update(UpdateHouseholdRequest $request, Household $household): RedirectResponse
    {
        $data = $request->validated();
        $headId = $data['head_resident_id'] ?? null;

        $before = $household->only(self::TRACKED) + ['member_count' => $household->member_count];

        DB::transaction(function () use ($household, $data, $headId): void {
            $household->fill([
                'household_number' => $data['household_number'],
                'address' => $data['address'],
                'purok_id' => $data['purok_id'] ?? null,
                'head_resident_id' => $headId,
                'house_type' => $data['house_type'],
                'ownership' => $data['ownership'],
                'status' => $data['status'],
            ])->save();

            if ($headId !== null) {
                Resident::findOrFail($headId)
                    ->forceFill(['household_id' => $household->id])
                    ->save();
            }

            // Clearing the head never moves members — only the count changes.
            $household->syncMemberCount();
        });

        $after = $data + ['member_count' => $household->member_count];
        [$changedBefore, $changedAfter] = $this->changedFields($before, $after);

        AuditLog::record('updated', 'household', $household->id, $changedBefore, $changedAfter);

        return redirect()
            ->route('households.show', $household)
            ->with('status', "Household {$household->household_number} updated.");
    }

    /* ------------------------------------------------------------------ */
    /* Delete (administrator only)                                        */
    /* ------------------------------------------------------------------ */

    public function destroy(Household $household): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $before = $household->only(self::TRACKED) + ['member_count' => $household->member_count];

        DB::transaction(function () use ($household): void {
            // Detach first, then drop the head reference, then the record.
            $household->members()->update(['household_id' => null]);
            $household->forceFill(['head_resident_id' => null])->save();
            $household->delete();
        });

        AuditLog::record('deleted', 'household', $household->id, $before, null);

        return redirect()
            ->route('households.index')
            ->with('status', "Household {$household->household_number} deleted.");
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Residents eligible to head a household: active and either free-standing
     * or already part of the household being edited. The sitting head is kept
     * in the list even if they have since been archived.
     *
     * @return Collection<int, Resident>
     */
    private function headOptions(?Household $household): Collection
    {
        return Resident::query()
            ->with('purok')
            ->where(function (Builder $query) use ($household): void {
                $query->where('status', Resident::STATUS_ACTIVE);

                if ($household?->head_resident_id) {
                    $query->orWhere('id', $household->head_resident_id);
                }
            })
            ->when(
                $household === null,
                fn (Builder $query): Builder => $query->whereNull('household_id'),
                fn (Builder $query): Builder => $query->where(function (Builder $query) use ($household): void {
                    $query->whereNull('household_id')
                        ->orWhere('household_id', $household->id);
                }),
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function purokFilter(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Only the fields that actually differ, so the audit trail stays readable.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function changedFields(array $before, array $after): array
    {
        $changedBefore = [];
        $changedAfter = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $this->scalar($before[$key] ?? null);

            if ($oldValue === $this->scalar($newValue)) {
                continue;
            }

            $changedBefore[$key] = $before[$key] ?? null;
            $changedAfter[$key] = $newValue;
        }

        return [$changedBefore, $changedAfter];
    }

    private function scalar(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null || $value === '' ? null : (string) $value;
    }
}
