<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The app-wide button family (.btn): one height, one radius, one weight,
 * so action controls stop drifting between pages — the account directory's
 * Review/View buttons were the visible symptom (text-sized, rounded-md).
 * Row actions also share a fixed width so ACTIONS columns line up, and the
 * only controls still wearing rounded-md are sidebar/toast chrome, which
 * the design locks keep on their own tokens.
 */
class ButtonSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_button_system_is_declared_in_the_components_layer_at_44px(): void
    {
        $css = File::get(resource_path('css/app.css'));

        // The family lives in @layer components so call-site utilities
        // (hidden, w-full, disabled:*) still override it.
        $this->assertStringContainsString('@layer components', $css);
        $this->assertStringContainsString('.btn {', $css);
        $this->assertStringContainsString('height: 2.75rem;', $css);   // exactly 44px — NFR6 taps
        $this->assertStringContainsString('border-radius: 0.5rem;', $css); // rounded-lg design lock
        $this->assertStringContainsString('.btn-row {', $css);         // fixed row-action width
    }

    public function test_shared_components_carry_the_button_family(): void
    {
        // table/actions.blade.php was removed as dead code (zero usages);
        // assert it stays gone so the family doesn't silently fork again.
        $this->assertFileDoesNotExist(resource_path('views/components/table/actions.blade.php'));
        $form = File::get(resource_path('views/components/form/actions.blade.php'));

        $this->assertStringContainsString('btn btn-neutral', $form);
        $this->assertStringContainsString('btn btn-primary', $form);
    }

    public function test_account_directory_actions_use_the_uniform_row_width(): void
    {
        $admin = User::factory()->admin()->create();

        // The directory renders the admin's own row, whose action is the
        // text-sized "View" link this redesign replaced.
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('btn btn-outline btn-row', false);
    }

    public function test_only_sidebar_and_toast_chrome_may_keep_rounded_md(): void
    {
        $allowed = [
            'app-layout.blade.php' => 'sidebar minimize/close chrome',
            'toasts.blade.php' => 'toast dismiss control',
        ];

        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->reject(fn ($file) => isset($allowed[$file->getFilename()]))
            ->filter(fn ($file) => str_contains($file->getContents(), 'rounded-md'))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame(
            [],
            $offenders,
            'Every control uses rounded-lg (design lock); rounded-md is sidebar/toast chrome only.'
        );
    }
}
