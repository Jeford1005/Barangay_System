<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOfficialRequest;
use App\Http\Requests\Admin\UpdateOfficialRequest;
use App\Models\AuditLog;
use App\Models\Official;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Barangay officials (the Sangguniang Barangay).
 *
 * Certificates are signed with the sitting Punong Barangay's name, so that
 * seat can never be deleted — an official can only be marked Inactive.
 */
class OfficialController extends Controller
{
    /**
     * Positions pre-seeded into the datalist. The field stays free text:
     * the barangay may add e.g. "Barangay Tanod Leader" later.
     *
     * @var list<string>
     */
    public const POSITION_OPTIONS = [
        'Punong Barangay',
        'Barangay Kagawad',
        'Barangay Secretary',
        'Barangay Treasurer',
        'SK Chairperson',
    ];

    /** Position of the official who signs every issued certificate. */
    private const PROTECTED_POSITION = 'Punong Barangay';

    public function index(): View
    {
        $officials = Official::query()
            ->orderByRaw("CASE WHEN position = 'Punong Barangay' THEN 0 ELSE 1 END") // head of the barangay first
            ->orderBy('position')
            ->orderByDesc('term_start')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.officials.index', [
            'officials' => $officials,
            'positions' => self::POSITION_OPTIONS,
        ]);
    }

    public function store(StoreOfficialRequest $request): RedirectResponse
    {
        $official = Official::create($request->validated());

        AuditLog::record('created', 'official', $official->id, null, $official->toArray());

        return redirect()
            ->route('admin.officials.index')
            ->with('status', "{$official->full_name} added as {$official->position}.");
    }

    public function update(UpdateOfficialRequest $request, Official $official): RedirectResponse
    {
        $before = $official->toArray();

        $official->fill($request->validated())->save();

        AuditLog::record('updated', 'official', $official->id, $before, $official->toArray());

        return back()->with('status', "{$official->full_name} updated.");
    }

    public function destroy(Official $official): RedirectResponse
    {
        // The Punong Barangay signs certificates on record — removing the row
        // would leave issued documents pointing at a name that no longer exists.
        if (strcasecmp(trim($official->position), self::PROTECTED_POSITION) === 0) {
            return back()->with(
                'error',
                'The Punong Barangay cannot be deleted — mark them Inactive instead.'
            );
        }

        $before = $official->toArray();
        $name = $official->full_name;

        $official->delete();

        AuditLog::record('deleted', 'official', $official->id, $before, null);

        return redirect()
            ->route('admin.officials.index')
            ->with('status', "{$name} removed from the officials list.");
    }
}
