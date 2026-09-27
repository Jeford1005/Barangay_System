<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Household;
use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $record = $model::onlyTrashed()->findOrFail($id);
        $record->restore();

        $this->audit($request, 'archive.restored', $type, $this->describe($type, $record));

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

        $record = $model::onlyTrashed()->findOrFail($id);

        // A purge must never orphan a record other tables still point at. Each
        // blocker is reported as a form error instead of surfacing as a foreign
        // key exception (HTTP 500) halfway through the transaction.
        if ($blocker = $this->purgeBlocker($type, $record)) {
            return back()->withErrors(['archive' => $blocker]);
        }

        $description = $this->describe($type, $record);

        DB::transaction(function () use ($record, $request, $type, $description) {
            $record->forceDelete();

            $this->audit($request, 'archive.purged', $type, $description);
        });

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
            $members = Resident::withTrashed()->where('household_id', $record->getKey())->count();

            if ($members > 0) {
                return "This household still has {$members} resident record(s) attached. Move or remove them before purging the household.";
            }
        }

        return null;
    }

    private function trashedQuery(string $type, Request $request): Builder
    {
        $search = mb_substr(strip_tags((string) $request->search), 0, 100);

        return match ($type) {
            'residents' => Resident::onlyTrashed()
                ->with('purok')
                ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })),
            'households' => Household::onlyTrashed()
                ->with('purok')
                ->when($search !== '', fn ($q) => $q->where('household_code', 'like', "%{$search}%")),
            'blotter' => Blotter::onlyTrashed()
                ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                    $q->where('case_number', 'like', "%{$search}%")
                        ->orWhere('complaint_type', 'like', "%{$search}%");
                })),
            'welfare' => Welfare::onlyTrashed()
                ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                    $q->where('beneficiary_name', 'like', "%{$search}%")
                        ->orWhere('program_name', 'like', "%{$search}%");
                })),
        };
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
