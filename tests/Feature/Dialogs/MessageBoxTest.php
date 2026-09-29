<?php

namespace Tests\Feature\Dialogs;

use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentRecordChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two shared message boxes — the styled replacement for the native
 * confirm() box and its red counterpart for client-side failures —
 * plus the copy rule they all follow: the header names the object,
 * the message asks the question, the buttons carry bare verbs.
 */
class MessageBoxTest extends TestCase
{
    use RefreshDatabase;

    private function approvedResident(): array
    {
        $user = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $user->id]);

        return [$user, $resident];
    }

    public function test_app_shell_ships_both_message_boxes_hidden(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        // Both cards ride on every app page, inert until JS opens them.
        $this->assertStringContainsString('id="confirm-dialog"', $html);
        $this->assertStringContainsString('id="error-dialog"', $html);
        $this->assertStringContainsString('class="error-dialog fixed inset-0 z-[60] hidden"', $html);
        $this->assertStringContainsString('role="alertdialog"', $html);

        // Motion hooks (all disabled under prefers-reduced-motion in app.css).
        $this->assertStringContainsString('dialog-icon-pop', $html);
        $this->assertStringContainsString('dialog-stagger', $html);
    }

    public function test_error_card_action_is_tinted_while_confirm_accepts_stay_filled(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        // A failed operation is not a destructive commit: its action is a
        // tinted red chip, while confirm dialogs keep the filled accept.
        $this->assertStringContainsString('bg-red-50 px-4 text-sm font-semibold text-red-700 hover:bg-red-100', $html);
        $this->assertStringContainsString('text-white bg-red-600 hover:bg-red-700', $html);
    }

    public function test_resident_portal_ships_the_error_dialog_and_the_size_precheck(): void
    {
        [$user] = $this->approvedResident();

        // 2048 KB mirrors the server rule (image|mimes:...|max:2048), so an
        // oversize photo is refused before it ever reaches the network.
        $this->actingAs($user)
            ->get('/my?edit=1')
            ->assertOk()
            ->assertSee('id="error-dialog"', false)
            ->assertSee('data-file-max-kb="2048"', false);
    }

    public function test_photo_removal_follows_the_header_question_bare_verb_rule(): void
    {
        [$user, $resident] = $this->approvedResident();
        $resident->update(['photo' => 'residents/profile.jpg']);

        $html = $this->actingAs($user)
            ->get('/my?edit=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-confirm="Remove your profile photo?"', $html);
        $this->assertStringContainsString('data-confirm-title="Remove photo"', $html);
        $this->assertStringContainsString('data-confirm-accept="Remove"', $html);
        $this->assertStringContainsString('data-confirm-dismiss="Cancel"', $html);
        $this->assertStringContainsString('data-confirm-icon="trash"', $html);
        // The button never repeats the header — the header already says it.
        $this->assertStringNotContainsString('data-confirm-accept="Remove photo"', $html);
    }

    public function test_login_page_ships_the_error_dialog_for_the_register_dialog(): void
    {
        // The create-account dialog raises the same red message box instead
        // of a native alert() when a submit fails server-side.
        $this->get('/login')
            ->assertOk()
            ->assertSee('id="error-dialog"', false)
            ->assertSee('class="error-dialog fixed inset-0 z-[60] hidden"', false);
    }

    public function test_cancel_flow_keeps_the_keep_cancel_pair_and_cross_icon(): void
    {
        [$user, $resident] = $this->approvedResident();
        $purok = Purok::factory()->create();
        ResidentRecordChange::create([
            'resident_id' => $resident->id,
            'requested_by' => $user->id,
            'changes' => ['occupation' => 'Teacher', 'purok_id' => $purok->id],
            'status' => 'Pending',
        ]);

        $html = $this->actingAs($user)
            ->get('/my')
            ->assertOk()
            ->getContent();

        // The verb itself is "Cancel", so the pair becomes Keep · Cancel —
        // the one exception to bare-verb duplication.
        $this->assertStringContainsString('data-confirm-accept="Cancel"', $html);
        $this->assertStringContainsString('data-confirm-dismiss="Keep"', $html);
        $this->assertStringContainsString('data-confirm-icon="x-mark"', $html);
        $this->assertStringNotContainsString('data-confirm-dismiss="Keep request"', $html);
    }
}
