<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWelfareRequest;
use App\Http\Requests\UpdateWelfareRequest;
use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Welfare assistance: staff take requests in, administrators review them
 * (approve / deny / release) against the granted amount.
 */
class WelfareController extends Controller
{
    /** @var list<string> */
    private const TRACKED = [
        'resident_id', 'assistance_type', 'requested_amount', 'amount',
        'status', 'request_date', 'notes',
    ];

    /* ------------------------------------------------------------------ */
    /* Listing                                                             */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = $this->filter($request->query('status'), Welfare::STATUSES);
        $type = $this->filter($request->query('assistance_type'), Welfare::ASSISTANCE_TYPES);

        $rows = Welfare::query()
            ->with('resident')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                $query->whereHas('resident', fn (Builder $query): Builder => $query->where('full_name', 'like', $like));
            })
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status))
            ->when($type !== null, fn (Builder $query): Builder => $query->where('assistance_type', $type))
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('welfare.index', [
            'rows' => $rows,
            'search' => $search,
            'status' => $status,
            'assistanceType' => $type,
            'pendingCount' => Welfare::where('status', 'Requested')->count(),
            'statuses' => Welfare::STATUSES,
            'assistanceTypes' => Welfare::ASSISTANCE_TYPES,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Intake (staff and administrators)                                   */
    /* ------------------------------------------------------------------ */

    public function create(): View
    {
        return view('welfare.create', [
            'welfare' => null,
            'residents' => $this->residentOptions(),
            'assistanceTypes' => Welfare::ASSISTANCE_TYPES,
        ]);
    }

    public function store(StoreWelfareRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Intake may never approve anything: the record starts as a request.
        $welfare = Welfare::create([
            'resident_id' => $data['resident_id'],
            'assistance_type' => $data['assistance_type'],
            'requested_amount' => $data['requested_amount'],
            'amount' => null,
            'status' => 'Requested',
            'request_date' => $data['request_date'],
            'notes' => $data['notes'] ?? null,
            'reviewed_by' => null,
        ]);

        AuditLog::record('created', 'welfare', $welfare->id, null, $welfare->only(self::TRACKED));

        return redirect()
            ->route('welfare.index')
            ->with('status', 'Assistance request recorded.');
    }

    /* ------------------------------------------------------------------ */
    /* Review (administrators only)                                        */
    /* ------------------------------------------------------------------ */

    public function edit(Welfare $welfare): View
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        return view('welfare.edit', [
            'welfare' => $welfare->load('resident'),
            'residents' => $this->residentOptions($welfare),
            'assistanceTypes' => Welfare::ASSISTANCE_TYPES,
            'statuses' => Welfare::STATUSES,
        ]);
    }

    public function update(UpdateWelfareRequest $request, Welfare $welfare): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $data = $request->validated();
        $data['amount'] = $this->normalizeAmount($data['amount'] ?? null);

        $before = $welfare->only(self::TRACKED);
        $welfare->fill($data)->save();

        $after = $welfare->only(self::TRACKED);
        [$changedBefore, $changedAfter] = $this->changedFields($before, $after);

        AuditLog::record('updated', 'welfare', $welfare->id, $changedBefore, $changedAfter);

        return redirect()
            ->route('welfare.index')
            ->with('status', 'Assistance request updated.');
    }

    public function destroy(Welfare $welfare): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        AuditLog::record('deleted', 'welfare', $welfare->id, $welfare->only(self::TRACKED), null);

        $welfare->delete();

        return redirect()
            ->route('welfare.index')
            ->with('status', 'Assistance request deleted.');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Active residents eligible for assistance, sorted by name. When editing,
     * the resident already on the record is kept in the list even if they have
     * been archived since, so the select never silently changes hands.
     *
     * @return Collection<int, Resident>
     */
    private function residentOptions(?Welfare $welfare = null): Collection
    {
        return Resident::query()
            ->where(function (Builder $query) use ($welfare): void {
                $query->where('status', Resident::STATUS_ACTIVE);

                if ($welfare?->resident_id) {
                    $query->orWhere('id', $welfare->resident_id);
                }
            })
            ->orderBy('full_name')
            ->get();
    }

    private function filter(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    /** A blank or zero grant is stored as null, never as 0. */
    private function normalizeAmount(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $amount = round((float) $value, 2);

        return $amount > 0 ? $amount : null;
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
            if ($this->scalar($before[$key] ?? null) === $this->scalar($newValue)) {
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

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return (string) $value;
    }
}
