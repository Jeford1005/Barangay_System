<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Concerns\Searchable;
use App\Models\Household;
use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveController extends Controller
{
    private const TYPES = [
        'residents' => [
            'model' => Resident::class,
            'label' => 'Residents',
            'singular' => 'Resident',
        ],
        'households' => [
            'model' => Household::class,
            'label' => 'Households',
            'singular' => 'Household',
        ],
        'blotter' => [
            'model' => Blotter::class,
            'label' => 'Blotter Cases',
            'singular' => 'Blotter case',
        ],
        'welfare' => [
            'model' => Welfare::class,
            'label' => 'Welfare Requests',
            'singular' => 'Welfare request',
        ],
    ];

    public function index(Request $request, string $type = 'residents')
    {
        if (! isset(static::TYPES[$type])) {
            abort(404);
        }

        $records = $this->trashedQuery($type, $request)
            ->orderBy('deleted_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $counts = [];
        foreach (static::TYPES as $key => $meta) {
            $counts[$key] = $meta['model']::onlyTrashed()->count();
        }

        return view('archive.index', [
            'type' => $type,
            'types' => static::TYPES,
            'records' => $records,
            'counts' => $counts,
        ]);
    }

    public function restore(Request $request, string $type, int $id)
    {
        $model = $this->requireType($type);

        // onlyTrashed() guarantees only archived records are restorable; the
        // row lock inside the transaction serializes concurrent restores of
        // the same record, and the audit entry commits atomically with it.
        DB::transaction(function () use ($request, $type, $model, $id) {
            $record = $model::onlyTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            $record->restore();

            $this->audit($request, 'archive.restored', $type, $this->describe($type, $record));
        });

        return redirect()
            ->route('archive.type', $type)
            ->with('success', static::TYPES[$type]['singular'].' restored successfully.');
    }

    public function destroy(Request $request, string $type, int $id)
    {
        if ($type === 'welfare') {
            return back()->withErrors(['archive' => 'Welfare assistance history cannot be permanently deleted from the browser.']);
        }

        $model = $this->requireType($type);

        // The blocker check runs inside the transaction on the locked row so
        // a reference created between the check and the delete cannot slip
        // through and surface as a foreign key exception (HTTP 500).
        try {
            $description = DB::transaction(function () use ($model, $request, $type, $id) {
                $record = $model::onlyTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();

                if ($blocker = $this->purgeBlocker($type, $record)) {
                    throw ValidationException::withMessages(['archive' => $blocker]);
                }

                $this->detachResidentReferences($type, $record);
                $description = $this->describe($type, $record);
                $record->forceDelete();

                $this->audit($request, 'archive.purged', $type, $description);

                return $description;
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('archive.type', $type)
            ->with('success', static::TYPES[$type]['singular'].' permanently deleted. This cannot be undone.');
    }

    /**
     * Explain why a record cannot be permanently deleted yet, or null when the
     * purge is safe to perform.
     */
    private function purgeBlocker(string $type, Model $record): ?string
    {
        if ($type === 'residents') {
            if ($record->user_id) {
                return 'This resident is linked to a user account. Unlink the account from the user record before purging the profile.';
            }

            $issuances = CertificateIssuance::withTrashed()->where('resident_id', $record->getKey())->count();
            $requests = CertificateRequest::query()->where('resident_id', $record->getKey())->count();

            if ($issuances > 0 || $requests > 0) {
                return "This resident has certificate history ({$issuances} issuance(s), {$requests} request(s)) that must be kept for accountability. Archive the profile instead.";
            }
        }

        if ($type === 'households') {
            // Only live members block the purge. Already-archived members are
            // detached in the purge transaction instead of blocking the
            // household forever.
            $members = Resident::where('household_id', $record->getKey())->count();

            if ($members > 0) {
                return "This household still has {$members} resident record(s) attached. Move or remove them before purging the household.";
            }
        }

        return null;
    }

    /**
     * Null out the nullable foreign keys that point at a record about to be
     * purged, mirroring each column's onDelete('set null') rule explicitly.
     * Relying solely on the database FK leaves orphans on stores where FK
     * enforcement is off, and purging a household must also release its
     * already-archived members instead of stranding them on a deleted id.
     */
    private function detachResidentReferences(string $type, Model $record): void
    {
        if ($type === 'residents') {
            $key = $record->getKey();

            Blotter::withTrashed()->where('complainant_id', $key)->update(['complainant_id' => null]);
            Blotter::withTrashed()->where('accused_id', $key)->update(['accused_id' => null]);
            Welfare::withTrashed()->where('beneficiary_id', $key)->update(['beneficiary_id' => null]);
            Household::withTrashed()->where('head_of_household_id', $key)->update(['head_of_household_id' => null]);
        }

        if ($type === 'households') {
            Resident::withTrashed()->where('household_id', $record->getKey())->update(['household_id' => null]);
        }
    }

    private function trashedQuery(string $type, Request $request): Builder
    {
        $search = Searchable::normalizeSearchTerm(mb_substr(strip_tags((string) $request->search), 0, 100)) ?? '';
        $like = '%'.self::escapeLike($search).'%';

        return match ($type) {
            'residents' => Resident::onlyTrashed()
                ->with('purok')
                ->when($search !== '', fn ($q) => $q->where(function ($q) use ($like) {
                    $q->whereRaw("first_name LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("last_name LIKE ? ESCAPE '\\'", [$like]);
                })),
            'households' => Household::onlyTrashed()
                ->with('purok')
                ->when($search !== '', fn ($q) => $q->whereRaw("household_code LIKE ? ESCAPE '\\'", [$like])),
            'blotter' => Blotter::onlyTrashed()
                ->when($search !== '', fn ($q) => $q->where(function ($q) use ($like) {
                    $q->whereRaw("case_number LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("complaint_type LIKE ? ESCAPE '\\'", [$like]);
                })),
            'welfare' => Welfare::onlyTrashed()
                ->when($search !== '', fn ($q) => $q->where(function ($q) use ($like) {
                    $q->whereRaw("beneficiary_name LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("program_name LIKE ? ESCAPE '\\'", [$like]);
                })),
        };
    }

    /**
     * Escape LIKE wildcards in user search input.
     * To be used with an explicit `ESCAPE '\\'` clause (portable across
     * MySQL and SQLite).
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function requireType(string $type): string
    {
        if (! isset(static::TYPES[$type])) {
            abort(404);
        }

        return static::TYPES[$type]['model'];
    }

    private function describe(string $type, $record): string
    {
        return match ($type) {
            'residents' => $record->full_name,
            'households' => $record->household_code,
            'blotter' => $record->case_number,
            'welfare' => $record->beneficiary_name,
        };
    }

    private function audit(Request $request, string $event, string $type, string $description): void
    {
        AuditLog::record(
            $event,
            Auth::id(),
            Auth::user()?->email,
            $request->ip(),
            $request->userAgent(),
            ['type' => $type, 'record' => $description],
        );
    }
}
