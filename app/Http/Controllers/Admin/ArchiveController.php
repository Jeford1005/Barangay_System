<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The archive holds resident records whose status is "Archived" — the system
 * has no soft deletes, so archiving simply takes a record out of the active
 * directory. From here an admin can restore a record, or purge it for good
 * (only when no linked account or certificate history would break).
 */
class ArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('search')), 0, 100);

        $residents = Resident::query()
            ->archived()
            ->with(['purok', 'household'])
            ->search($search !== '' ? $search : null)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('archive.index', [
            'residents' => $residents,
            'search' => $search,
        ]);
    }

    /** Put an archived record back into the active resident directory. */
    public function restore(Resident $resident): RedirectResponse
    {
        if (! $resident->isArchived()) {
            return back()->with('error', "{$resident->full_name} is not archived.");
        }

        $resident->update(['status' => Resident::STATUS_ACTIVE]);

        AuditLog::record(
            'restored',
            'resident',
            $resident->id,
            ['status' => Resident::STATUS_ARCHIVED],
            ['status' => Resident::STATUS_ACTIVE],
        );

        return back()->with('status', "{$resident->full_name} restored to the active resident directory.");
    }

    /** Irreversible delete of an archived record. */
    public function destroy(Resident $resident): RedirectResponse
    {
        if (! $resident->isArchived()) {
            return back()->with(
                'error',
                'Only archived records can be deleted permanently. Archive this record first.'
            );
        }

        if (User::where('resident_id', $resident->id)->exists()) {
            return back()->with(
                'error',
                "{$resident->full_name} is linked to a sign-in account. Unlink the account first."
            );
        }

        if ($resident->certificateIssuances()->exists() || $resident->certificateRequests()->exists()) {
            return back()->with(
                'error',
                'Certificate history cannot be permanently deleted. Keep this record archived.'
            );
        }

        $before = $resident->toArray(); // last known attributes, kept for the audit trail
        $name = $resident->full_name;

        $resident->delete();

        AuditLog::record('purged', 'resident', $resident->id, $before, null);

        return redirect()
            ->route('archive.index')
            ->with('status', "{$name} permanently deleted.");
    }
}
