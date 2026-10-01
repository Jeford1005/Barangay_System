<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResidentContactUpdateRequest;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentRecordChange;
use App\Notifications\ResidentEmailChangedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ResidentPortalController extends Controller
{
    /**
     * The resident's own portal home: the record on file, the contact details
     * they control themselves, their household, and — since the corrections
     * module was folded in — the correction form behind `?edit=1` plus any
     * requests already sent.
     */
    public function index(Request $request): View
    {
        $resident = $request->user()?->residentProfile;

        abort_if(! $resident, 404, 'No resident profile is linked to this account. Please contact the barangay office.');
        abort_if($resident->status !== 'Active', 403, 'This resident record is archived. Please contact the barangay office.');

        $householdMembers = $resident->household_id
            ? Resident::where('household_id', $resident->household_id)
                ->whereKeyNot($resident->id)
                ->orderByDesc('is_household_head')
                ->orderBy('last_name')
                ->get(['first_name', 'middle_name', 'last_name', 'suffix', 'is_household_head'])
            : collect();

        // The correction form only renders in edit mode, so its two lookup
        // queries only run when it is actually on screen.
        $editing = $request->boolean('edit');
        $puroks = $editing ? Purok::orderBy('name')->pluck('name', 'id') : collect();
        $households = $editing ? Household::orderBy('household_code')->pluck('household_code', 'id') : collect();

        return view('resident.portal', [
            'resident' => $resident->load(['purok', 'household']),
            'householdMembers' => $householdMembers,
            'changes' => ResidentRecordChange::where('resident_id', $resident->id)->latest('id')->paginate(10)->withQueryString(),
            'editing' => $editing,
            'puroks' => $puroks,
            'households' => $households,
        ]);
    }

    /**
     * Update the resident's own contact details (scoped to their own row).
     */
    public function updateContact(ResidentContactUpdateRequest $request)
    {
        $user = $request->user();
        $resident = $user?->residentProfile;

        abort_if(! $resident, 404);
        abort_if($resident->status !== 'Active', 403, 'Archived resident profiles cannot be updated.');

        $validated = $request->validated();
        $newEmail = trim((string) ($validated['email'] ?? ''));
        $emailChanged = $newEmail !== '' && strcasecmp($newEmail, (string) $user->email) !== 0;
        $oldEmail = $user->email;
        $oldPhoto = $resident->photo;
        // The upload is only captured here. It is stored inside the
        // transaction below under a random hashed name, and deleted again if
        // the transaction fails, so a rolled-back save never leaves an orphan
        // file behind.
        $photoFile = $request->hasFile('photo') ? $request->file('photo') : null;
        $newPhoto = null;

        try {
            DB::transaction(function () use ($request, $user, $resident, $validated, $newEmail, $emailChanged, $oldEmail, $photoFile, &$newPhoto) {
                $residentData = [
                    'phone_number' => $validated['phone_number'] ?? null,
                    'address' => $validated['address'],
                ];

                if ($newEmail !== '') {
                    $residentData['email'] = $newEmail;
                }

                if ($photoFile) {
                    $newPhoto = $photoFile->storeAs('residents', $photoFile->hashName(), 'local');
                    $residentData['photo'] = $newPhoto;
                }

                $resident->update($residentData);

                if ($newPhoto) {
                    AuditLog::record(
                        'resident.photo_updated',
                        $user->id,
                        $user->email,
                        $request->ip(),
                        $request->userAgent(),
                        ['resident_id' => $resident->id, 'photo' => $newPhoto],
                    );
                }

                if ($emailChanged) {
                    $user->update(['email' => $newEmail]);

                    DB::table('password_reset_tokens')
                        ->whereIn('email', array_unique([$oldEmail, $newEmail]))
                        ->delete();

                    AuditLog::record(
                        'resident.email_updated',
                        $user->id,
                        $user->email,
                        $request->ip(),
                        $request->userAgent(),
                        ['resident_id' => $resident->id, 'old_email' => $oldEmail, 'new_email' => $newEmail],
                    );
                }
            });
        } catch (\Throwable $e) {
            if ($newPhoto) {
                Storage::disk('local')->delete($newPhoto);
            }

            throw $e;
        }

        // The phone/address save itself is accountable too: photo and email
        // changes already log their own events inside the transaction, but a
        // plain contact edit would otherwise leave no trail.
        AuditLog::record(
            'resident.contact_updated',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
            ['resident_id' => $resident->id],
        );

        if ($emailChanged) {
            $user->notify(new ResidentEmailChangedNotification($oldEmail, $newEmail));

            // Warn the previous inbox too: without this, an account takeover
            // via email change is invisible to the original owner. Mail
            // failure must never 500 the already-committed change.
            try {
                Notification::route('mail', $oldEmail)
                    ->notify(new ResidentEmailChangedNotification($oldEmail, $newEmail));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($newPhoto && $oldPhoto) {
            // Photos taken before the move to the private disk still live on
            // the public disk, so try both.
            Storage::disk('local')->delete($oldPhoto);
            Storage::disk('public')->delete($oldPhoto);
        }

        return redirect()->route('resident.portal')->with('success', 'Contact details updated.');
    }

    /**
     * Remove the resident's own profile photo.
     *
     * This is the one path that clears the column: the contact form decides
     * with `hasFile()`, so for it a photo can only ever be replaced. It is a
     * standalone DELETE rather than a flag on that form on purpose - the
     * resident's phone, address and email must not have to validate for a
     * photo removal to go through.
     */
    public function destroyPhoto(Request $request)
    {
        $user = $request->user();
        $resident = $user?->residentProfile;

        abort_if(! $resident, 404);
        abort_if($resident->status !== 'Active', 403, 'Archived resident profiles cannot be updated.');

        $photo = $resident->photo;

        if (blank($photo)) {
            return back()->with('success', 'There is no profile photo to remove.');
        }

        // Clear the column first: a file that fails to delete leaves an
        // orphan, while a saved path pointing at nothing is a broken image.
        $resident->update(['photo' => null]);

        // Photos taken before the move to the private disk still live on the
        // public disk, so try both.
        Storage::disk('local')->delete($photo);
        Storage::disk('public')->delete($photo);

        AuditLog::record(
            'resident.photo_removed',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
            ['resident_id' => $resident->id],
        );

        return back()->with('success', 'Profile photo removed.');
    }
}
