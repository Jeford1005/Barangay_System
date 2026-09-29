<?php

namespace Tests\Feature\Resident;

use App\Models\Official;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The officials directory is the one resident page that reads a table the
 * resident does not own: `officials` is public-facing reference data, so the
 * page lists it and does nothing else - no filters, no actions, no write
 * route to fall through to.
 */
class ResidentOfficialsTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        return User::factory()->resident()->create();
    }

    public function test_resident_can_open_the_officials_directory(): void
    {
        Official::factory()->create([
            'first_name' => 'Jose',
            'middle_name' => null,
            'last_name' => 'Fernandez',
            'position' => 'Barangay Captain',
            'office' => 'Barangay Hall',
            'status' => 'Active',
            'term_start' => '2023-01-01',
            'term_end' => '2026-12-31',
        ]);

        $this->actingAs($this->resident())
            ->get('/my/officials')
            ->assertOk()
            ->assertSee('Barangay Officials')
            ->assertSee('Jose Fernandez')
            ->assertSee('Barangay Captain')
            ->assertSee('Barangay Hall')
            ->assertSee('Jan 1, 2023 – Dec 31, 2026')
            ->assertSee(route('resident.officials'), false);
    }

    public function test_the_directory_lists_only_serving_officials(): void
    {
        Official::factory()->create(['first_name' => 'Serving', 'status' => 'Active']);
        Official::factory()->create(['first_name' => 'Retired', 'status' => 'Inactive']);

        $left = Official::factory()->create(['first_name' => 'Former', 'status' => 'Active']);
        $left->delete();

        $this->actingAs($this->resident())
            ->get('/my/officials')
            ->assertOk()
            ->assertSee('Serving')
            ->assertDontSee('Retired')
            ->assertDontSee('Former');
    }

    public function test_the_directory_reads_in_position_order(): void
    {
        Official::factory()->create(['first_name' => 'Zena', 'position' => 'Barangay Treasurer']);
        Official::factory()->create(['first_name' => 'Amara', 'position' => 'Barangay Captain']);

        $content = $this->actingAs($this->resident())
            ->get('/my/officials')
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($content, 'Zena'),
            strpos($content, 'Amara'),
            'The captain should be listed before the treasurer.',
        );
    }

    public function test_an_empty_directory_says_so_instead_of_erroring(): void
    {
        $this->actingAs($this->resident())
            ->get('/my/officials')
            ->assertOk()
            ->assertSee('No barangay officials are listed yet.');
    }

    public function test_office_users_are_redirected_away_from_the_directory(): void
    {
        foreach (['admin', 'staff', 'official'] as $role) {
            $this->actingAs(User::factory()->create(['user_type' => $role]))
                ->get('/my/officials')
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/my/officials')->assertRedirect(route('login'));
    }
}
