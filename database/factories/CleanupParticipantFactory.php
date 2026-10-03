<?php

namespace Database\Factories;

use App\Models\CleanupDrive;
use App\Models\CleanupParticipant;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class CleanupParticipantFactory extends Factory
{
    protected $model = CleanupParticipant::class;

    public function definition(): array
    {
        return [
            'drive_id' => CleanupDrive::factory(),
            'resident_id' => Resident::factory(),
            'attended' => false,
            'hours' => null,
            'checked_in_at' => null,
        ];
    }

    public function attended(): static
    {
        return $this->state(fn () => [
            'attended' => true,
            'hours' => $this->faker->randomFloat(1, 1, 8),
            'checked_in_at' => now()->subHours(2),
        ]);
    }
}
