<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResidentContactUpdateRequest;
use App\Models\AuditLog;
use App\Models\Resident;
use App\Notifications\ResidentEmailChangedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ResidentPortalController extends Controller
{
    /**
     * The resident's own portal home.
     */
    public function index(Request $request): View
    {
        $resident = $request->user()->residentProfile;

        abort_if(! $resident, 404, 'No resident profile is linked to this account. Please contact the barangay office.');
        abort_if($resident->status !== 'Active', 403, 'This resident record is archived. Please contact the barangay office.');

        $householdMembers = $resident->household_id
            ? Resident::where('household_id', $resident->household_id)
                ->whereKeyNot($resident->id)
                ->orderByDesc('is_household_head')
                ->orderBy('last_name')
                ->get(['first_name', 'middle_name', 'last_name', 'suffix', 'is_household_head'])
            : collect();

        return view('resident.portal', [
            'resident' => $resident->load(['purok', 'household']),
            'householdMembers' => $householdMembers,
        ]);
    }

    /**
     * Update the resident's own contact details (scoped to their own row).
     */
    public function updateContact(ResidentContactUpdateRequest $request)
    {
        $user = $request->user();
        $resident = $user->residentProfile;

        abort_if(! $resident, 404);
        abort_if($resident->status !== 'Active', 403, 'Archived resident profiles cannot be updated.');

        $validated = $request->validated();
        $newEmail = trim((string) ($validated['email'] ?? ''));
        $emailChanged = $newEmail !== '' && strcasecmp($newEmail, $user->email) !== 0;
        $oldEmail = $user->email;
        $oldPhoto = $resident->photo;
        $newPhoto = $request->hasFile('photo')
            ? $request->file('photo')->store('residents', 'local')
            : null;

        DB::transaction(function () use ($request, $user, $resident, $validated, $newEmail, $emailChanged, $oldEmail, $newPhoto) {
            $residentData = [
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'],
            ];

            if ($newEmail !== '') {
                $residentData['email'] = $newEmail;
            }

            if ($newPhoto) {
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

        if ($emailChanged) {
            $user->notify(new ResidentEmailChangedNotification($oldEmail, $newEmail));
        }

        if ($newPhoto && $oldPhoto) {
            // Photos taken before the move to the private disk still live on
            // the public disk, so try both.
            Storage::disk('local')->delete($oldPhoto);
            Storage::disk('public')->delete($oldPhoto);
        }

        return back()->with('success', 'Contact details updated.');
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
