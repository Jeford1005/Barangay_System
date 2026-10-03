<?php

namespace Database\Factories;

use App\Models\CleanupDrive;
use Illuminate\Database\Eloquent\Factories\Factory;

class CleanupDriveFactory extends Factory
{
    protected $model = CleanupDrive::class;

    public function definition(): array
    {
        return [
            'title' => ucfirst($this->faker->words(3, true)).' cleanup drive',
            'description' => $this->faker->sentence(10),
            'purok_id' => null,
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+2 months'),
            'status' => 'Scheduled',
            'created_by' => null,
        ];
    }

    public function ongoing(): static
    {
        return $this->state(fn () => [
            'status' => 'Ongoing',
            'scheduled_at' => now()->subDay(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'Completed',
            'scheduled_at' => now()->subWeek(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'Cancelled',
            'scheduled_at' => now()->addWeek(),
        ]);
    }
}
