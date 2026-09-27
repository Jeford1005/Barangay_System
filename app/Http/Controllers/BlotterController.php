<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlotterRequest;
use App\Http\Requests\UpdateBlotterRequest;
use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\Official;
use App\Models\Purok;
use App\Models\SequenceCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Blotter / case sheet module.
 *
 * Every entry gets a control number (BLTR-2026-0001) reserved atomically on
 * create, and every write is mirrored into the audit trail.
 */
class BlotterController extends Controller
{
    /** Attributes captured in audit before/after snapshots. */
    private const AUDIT_FIELDS = [
        'case_number', 'incident_date', 'incident_time', 'incident_type', 'location',
        'purok_id', 'complainant_name', 'complainant_contact', 'respondent_name',
        'respondent_contact', 'narrative', 'handling_officer', 'arrest_made',
        'status', 'resolution_notes', 'recorded_by',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(Blotter::STATUSES)],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        $rows = Blotter::query()
            ->with(['purok', 'recorder'])
            ->search($search)
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('blotter.index', [
            'rows' => $rows,
            'q' => $search,
            'status' => $status ?? '',
            'openCount' => Blotter::whereIn('status', ['Open', 'Pending'])->count(),
        ]);
    }

    public function create(): View
    {
        return view('blotter.create', [
            'blotter' => null,
            'puroks' => Purok::orderBy('code')->get(),
            'officers' => $this->officerOptions(null),
        ]);
    }

    public function store(StoreBlotterRequest $request): RedirectResponse
    {
        $blotter = DB::transaction(function () use ($request): Blotter {
            $data = $request->validated();

            $data['case_number'] = SequenceCounter::nextControlNumber('BLTR');
            $data['recorded_by'] = auth()->id();

            return Blotter::create($data);
        });

        AuditLog::record('created', 'blotter', $blotter->id, null, $this->snapshot($blotter));

        return redirect()
            ->route('blotter.index')
            ->with('status', "Case {$blotter->case_number} was recorded.");
    }

    public function edit(Blotter $blotter): View
    {
        return view('blotter.edit', [
            'blotter' => $blotter->load('recorder'),
            'puroks' => Purok::orderBy('code')->get(),
            'officers' => $this->officerOptions($blotter->handling_officer),
        ]);
    }

    public function update(UpdateBlotterRequest $request, Blotter $blotter): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('blotter.manage') ?? false, 403);

        $validated = $request->validated();

        $before = [];
        $after = [];

        foreach ($validated as $field => $value) {
            $original = $blotter->getRawOriginal($field);

            if ($this->normalize($field, $original) !== $this->normalize($field, $value)) {
                $before[$field] = $this->normalize($field, $original);
                $after[$field] = $this->normalize($field, $value);
            }
        }

        $blotter->fill($validated)->save();

        AuditLog::record('updated', 'blotter', $blotter->id, $before ?: null, $after ?: null);

        return redirect()
            ->route('blotter.index')
            ->with('status', "Case {$blotter->case_number} was updated.");
    }

    /** Administrator-only removal — the control number is never reused. */
    public function destroy(Blotter $blotter): RedirectResponse
    {
        $snapshot = $this->snapshot($blotter);

        AuditLog::record('deleted', 'blotter', $blotter->id, $snapshot, null);

        $blotter->delete();

        return redirect()
            ->route('blotter.index')
            ->with('status', "Case {$snapshot['case_number']} was deleted.");
    }

    /** Standalone A4 case sheet — prints on its own, outside the app layout. */
    public function print(Blotter $blotter): View
    {
        return view('blotter.print', [
            'blotter' => $blotter->load(['purok', 'recorder']),
            'punong' => Official::punongBarangay(),
            'printedAt' => now(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Active officials for the handling-officer dropdown, keeping whatever
     * name is already stored on the case even if that official resigned.
     *
     * @return array<string, string>
     */
    private function officerOptions(?string $current): array
    {
        $options = Official::where('status', Official::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->pluck('full_name', 'full_name')
            ->all();

        $current = $current !== null ? trim($current) : '';

        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
            ksort($options);
        }

        return $options;
    }

    /** Plain-array snapshot used for audit before/after payloads. */
    private function snapshot(Blotter $blotter): array
    {
        $data = [];

        foreach (self::AUDIT_FIELDS as $field) {
            $value = $blotter->getAttribute($field);

            $data[$field] = match ($field) {
                'incident_date' => $value?->format('Y-m-d'),
                'incident_time' => $value?->format('H:i'),
                default => $value,
            };
        }

        return $data;
    }

    /** Normalise raw / submitted values so a change can be detected. */
    private function normalize(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        return match ($field) {
            'incident_date' => substr($value, 0, 10),
            'incident_time' => substr($value, 0, 5),
            'purok_id', 'recorded_by' => (string) (int) $value,
            default => $value,
        };
    }
}
