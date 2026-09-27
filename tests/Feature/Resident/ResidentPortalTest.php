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
