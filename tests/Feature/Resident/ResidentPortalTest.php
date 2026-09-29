<?php

namespace Tests\Feature\Resident;

use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentEmailChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResidentPortalTest extends TestCase
{
    use RefreshDatabase;

    private function approvedResident(): array
    {
        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $resident = Resident::factory()->create([
            'user_id' => $user->id,
            'phone_number' => '09170000000',
            'address' => 'Old address',
        ]);

        return [$user, $resident];
    }

    public function test_resident_sees_their_own_profile(): void
    {
        [$user, $resident] = $this->approvedResident();

        $this->actingAs($user)
            ->get('/my')
            ->assertOk()
            ->assertSee($resident->first_name)
            ->assertSee($resident->last_name)
            ->assertSee('Old address');
    }

    public function test_profile_page_carries_only_user_information(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->get('/my')
            ->assertOk()
            ->assertSee('Resident record')
            ->assertSee('Contact details')
            ->assertSee('Household members')
            ->assertDontSee('My Profile')
            ->assertDontSee('My Requests')
            ->assertDontSee('Requests &amp; corrections')
            // The correction form is collapsed until Profile's Edit is pressed.
            ->assertDontSee('Request a profile correction')
            ->assertDontSee('awaiting review')
            ->assertDontSee('ready to download');
    }

    public function test_rendered_contact_form_uses_the_put_route(): void
    {
        [$user] = $this->approvedResident();

        $html = $this->actingAs($user)
            ->get('/my')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="_method" value="PUT"', $html);
    }

    public function test_resident_can_update_own_contact_details(): void
    {
        [$user, $resident] = $this->approvedResident();

        $this->actingAs($user)
            ->put('/my/contact', [
                'phone_number' => '09171234567',
                'address' => 'New address 456',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('09171234567', $resident->fresh()->phone_number);
        $this->assertSame('New address 456', $resident->fresh()->address);
    }

    public function test_resident_can_upload_a_profile_photo(): void
    {
        Storage::fake('local');
        [$user, $resident] = $this->approvedResident();

        $this->actingAs($user)
            ->put('/my/contact', [
                'phone_number' => $resident->phone_number,
                'address' => $resident->address,
                'photo' => UploadedFile::fake()->createWithContent('profile.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
            ])
            ->assertRedirect();

        $photo = $resident->fresh()->photo;
        $this->assertNotNull($photo);

        // Stored privately, and readable back through the authorized route.
        Storage::disk('local')->assertExists($photo);
        Storage::disk('public')->assertMissing($photo);

        $this->actingAs($user)->get(route('resident.photo'))->assertOk();
    }

    public function test_resident_can_remove_their_profile_photo(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$user, $resident] = $this->approvedResident();
        $resident->update(['photo' => 'residents/old.jpg']);
        Storage::disk('local')->put('residents/old.jpg', 'bytes');

        $this->actingAs($user)
            ->delete(route('resident.photo.destroy'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Profile photo removed.');

        // Column cleared first, then the file, and the action is accountable.
        $this->assertNull($resident->fresh()->photo);
        Storage::disk('local')->assertMissing('residents/old.jpg');
        $this->assertDatabaseHas('audit_logs', ['event' => 'resident.photo_removed']);
    }

    public function test_removal_is_offered_only_when_a_photo_exists(): void
    {
        [$user, $resident] = $this->approvedResident();

        // Without a photo there is nothing to remove, and nothing is rendered.
        $this->actingAs($user)
            ->get('/my')
            ->assertOk()
            ->assertDontSee('form="remove-photo-form"', false);

        // The guard caches the profile relation on this shared user instance
        // during that first request, so drop it to see what a real next
        // request would see.
        $resident->update(['photo' => 'residents/old.jpg']);
        $user->unsetRelation('residentProfile');

        $this->actingAs($user)
            ->get('/my')
            ->assertOk()
            // The Remove button joins its own form instead of the contact form,
            // so removing never depends on the contact fields validating.
            ->assertSee('form="remove-photo-form"', false)
            ->assertSee(route('resident.photo.destroy'), false);
    }

    public function test_removal_without_a_photo_is_a_no_op(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->delete(route('resident.photo.destroy'))
            ->assertRedirect()
            ->assertSessionHas('success', 'There is no profile photo to remove.');
    }

    public function test_archived_resident_cannot_remove_their_photo(): void
    {
        [$user, $resident] = $this->approvedResident();
        $resident->update(['status' => 'Archived', 'photo' => 'residents/old.jpg']);

        $this->actingAs($user)->delete(route('resident.photo.destroy'))->assertForbidden();

        $this->assertNotNull($resident->fresh()->photo);
    }

    public function test_office_users_cannot_remove_a_resident_photo(): void
    {
        foreach (['admin', 'staff', 'official'] as $role) {
            $this->actingAs(User::factory()->create(['user_type' => $role, 'status' => 'approved']))
                ->delete(route('resident.photo.destroy'))
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_guests_cannot_remove_a_photo(): void
    {
        $this->delete(route('resident.photo.destroy'))->assertRedirect(route('login'));
    }

    public function test_resident_can_change_login_email_with_current_password(): void
    {
        [$user, $resident] = $this->approvedResident();
        Notification::fake();

        $this->actingAs($user)
            ->put('/my/contact', [
                'phone_number' => $resident->phone_number,
                'address' => $resident->address,
                'email' => 'new-resident@example.com',
                'email_confirmation' => 'new-resident@example.com',
                'current_password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('new-resident@example.com', $user->fresh()->email);
        $this->assertSame('new-resident@example.com', $resident->fresh()->email);
        $this->assertDatabaseHas('audit_logs', ['event' => 'resident.email_updated']);
        Notification::assertSentTo($user->fresh(), ResidentEmailChangedNotification::class);
    }

    public function test_email_change_requires_the_current_password(): void
    {
        [$user, $resident] = $this->approvedResident();

        $this->actingAs($user)
            ->put('/my/contact', [
                'phone_number' => $resident->phone_number,
                'address' => $resident->address,
                'email' => 'new-resident@example.com',
                'email_confirmation' => 'new-resident@example.com',
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertNotSame('new-resident@example.com', $user->fresh()->email);
    }

    public function test_resident_cannot_touch_another_residents_record(): void
    {
        [$user] = $this->approvedResident();
        $other = Resident::factory()->create(['address' => 'Someone else']);

        // There is no id parameter in the route at all — the controller scopes
        // to the signed-in user's own record. This pins that invariant.
        $this->actingAs($user)
            ->put('/my/contact', [
                'phone_number' => '09179999999',
                'address' => 'Hacked',
            ])
            ->assertRedirect();

        $this->assertSame('Someone else', $other->fresh()->address);
    }

    public function test_archived_resident_cannot_access_portal(): void
    {
        [$user, $resident] = $this->approvedResident();
        $resident->update(['status' => 'Archived']);

        $this->actingAs($user)
            ->get('/my')
            ->assertForbidden();
    }

    public function test_archived_resident_cannot_update_contact_details(): void
    {
        [$user, $resident] = $this->approvedResident();
        $resident->update(['status' => 'Archived']);

        $this->actingAs($user)
            ->put('/my/contact', [
                'phone_number' => $resident->phone_number,
                'address' => 'Changed after archiving',
            ])
            ->assertForbidden();
    }

    public function test_admins_are_redirected_away_from_the_portal(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin', 'status' => 'approved']);

        $this->actingAs($admin)
            ->get('/my')
            ->assertRedirect(route('dashboard'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/my')->assertRedirect(route('login'));
    }

    public function test_contact_update_rejects_letters_in_phone_numbers(): void
    {
        [$user, $resident] = $this->approvedResident();

        $this->actingAs($user)
            ->put('/my/contact', [
                'phone_number' => 'phone-number',
                'address' => 'New address',
            ])
            ->assertSessionHasErrors('phone_number');

        $this->assertSame('09170000000', $resident->fresh()->phone_number);
    }

    public function test_contact_update_validates_input(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->put('/my/contact', ['address' => ''])
            ->assertSessionHasErrors('address');
    }
}
