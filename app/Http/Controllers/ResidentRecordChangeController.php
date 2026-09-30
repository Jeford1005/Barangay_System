<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentRecordChange;
use App\Services\HouseholdResidentSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResidentRecordChangeController extends Controller
{
    // The resident-facing index() lived here. The correction form and the
    // request list now render on the Profile page (?edit=1), so the portal
    // controller owns them and only submission and cancellation remain.

    public function store(Request $request): RedirectResponse
    {
        $resident = $this->requireProfile($request);
        $validated = $request->validate([
            'occupation' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'residency_status' => ['nullable', 'string', 'max:50'],
            'purok_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::exists('puroks', 'id')],
            'household_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::exists('households', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'purok_id.exists' => 'The selected purok is no longer available.',
            'household_id.exists' => 'The selected household is no longer available.',
        ]);

        $changes = collect([
            'occupation' => $validated['occupation'] ?? null,
            'religion' => $validated['religion'] ?? null,
            'residency_status' => $validated['residency_status'] ?? null,
            'purok_id' => $validated['purok_id'] ?? null,
            'household_id' => $validated['household_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->filter(fn ($value) => $value !== null && $value !== '')->all();

        if (collect($changes)->except('notes')->isEmpty()) {
            return back()->withErrors(['changes' => 'Select at least one detail to request a correction.']);
        }

        $change = ResidentRecordChange::create([
            'resident_id' => $resident->id,
            'requested_by' => $request->user()->id,
            'changes' => $changes,
            'status' => 'Pending',
        ]);

        AuditLog::record(
            'resident.change_requested',
            $request->user()->id,
            $request->user()->email,
            $request->ip(),
            $request->userAgent(),
            ['resident_id' => $resident->id, 'change_id' => $change->id, 'fields' => array_keys($changes)],
        );

        return redirect()->route('resident.portal')
            ->with('success', 'Your correction request was submitted for office review.');
    }

    public function cancel(Request $request, ResidentRecordChange $change): RedirectResponse
    {
        $resident = $this->requireProfile($request);
        abort_unless($change->resident_id === $resident->id, 403);
        abort_unless($change->status === 'Pending', 422, 'Only pending requests can be cancelled.');

        $change->update(['status' => 'Cancelled']);

        return back()->with('success', 'Correction request cancelled.');
    }

    public function indexForAdmin(Request $request): View
    {
        $status = (string) $request->input('status', 'Pending');
        $showAll = $status === '' || $status === 'All';
        if (! $showAll && ! in_array($status, ['Pending', 'Approved', 'Rejected', 'Cancelled'], true)) {
            $status = 'Pending';
        }

        $changes = ResidentRecordChange::with(['resident', 'requester', 'reviewer'])
            ->when(! $showAll, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.resident-changes', [
            'changes' => $changes,
            'status' => $showAll ? 'All' : $status,
            // Resolve raw purok_id/household_id values to names in the table
            // instead of showing bare foreign keys.
            'purokNames' => Purok::pluck('name', 'id'),
            'householdCodes' => Household::pluck('household_code', 'id'),
        ]);
    }

    public function approve(Request $request, ResidentRecordChange $change, HouseholdResidentSync $sync): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('resident-changes.decide'), 403);

        $validated = $request->validate(['review_note' => ['nullable', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $change, $validated, $sync) {
            $locked = ResidentRecordChange::whereKey($change->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'Pending', 422, 'Only pending requests can be approved.');

            $resident = Resident::whereKey($locked->resident_id)->lockForUpdate()->firstOrFail();
            if ($resident->status !== 'Active') {
                throw ValidationException::withMessages([
                    'resident_id' => 'Archived resident profiles cannot receive correction approvals.',
                ]);
            }
            $previousHouseholdId = $resident->household_id;
            $changes = array_intersect_key($locked->changes, array_flip([
                'occupation',
                'religion',
                'residency_status',
                'purok_id',
                'household_id',
            ]));

            if (array_key_exists('purok_id', $changes) && ! Purok::whereKey($changes['purok_id'])->exists()) {
                throw ValidationException::withMessages(['purok_id' => 'The requested purok is no longer available.']);
            }
            if (array_key_exists('household_id', $changes)) {
                $householdExists = Household::whereKey($changes['household_id'])
                    ->whereNull('deleted_at')
                    ->whereIn('status', ['Occupied', 'Under Construction'])
                    ->exists();
                if (! $householdExists) {
                    throw ValidationException::withMessages(['household_id' => 'The requested household is no longer available.']);
                }
            }

            $resident->fill($changes);
            $resident->updated_by = $request->user()->id;
            $resident->save();
            $sync->syncResidentAfterSave($resident, $previousHouseholdId);

            $locked->update([
                'status' => 'Approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
            ]);

            AuditLog::record(
                'resident.change_approved',
                $request->user()->id,
                $request->user()->email,
                $request->ip(),
                $request->userAgent(),
                ['resident_id' => $resident->id, 'change_id' => $locked->id, 'fields' => array_keys($changes)],
            );
        });

        return redirect()->route('admin.resident-changes.index')
            ->with('success', 'Resident correction request approved.');
    }

    public function reject(Request $request, ResidentRecordChange $change): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('resident-changes.decide'), 403);

        $validated = $request->validate(['review_note' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $change, $validated) {
            $locked = ResidentRecordChange::whereKey($change->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'Pending', 422, 'Only pending requests can be rejected.');

            $locked->update([
                'status' => 'Rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'],
            ]);

            AuditLog::record(
                'resident.change_rejected',
                $request->user()->id,
                $request->user()->email,
                $request->ip(),
                $request->userAgent(),
                ['resident_id' => $locked->resident_id, 'change_id' => $locked->id],
            );
        });

        return redirect()->route('admin.resident-changes.index')
            ->with('success', 'Resident correction request rejected.');
    }

    private function requireProfile(Request $request): Resident
    {
        $resident = $request->user()?->residentProfile;
        abort_if(! $resident, 404, 'No resident profile is linked to this account.');
        abort_if($resident->status !== 'Active', 403, 'Archived resident profiles cannot submit correction requests.');

        return $resident;
    }
}
