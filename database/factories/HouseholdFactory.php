<?php

namespace Database\Factories;

use App\Models\Purok;
use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_code' => 'HH-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'purok_id' => Purok::factory(),
            'sitio' => null,
            'street' => $this->faker->streetName(),
            'barangay' => 'Sample Barangay',
            'municipality' => 'Sample Municipality',
            'province' => 'Sample Province',
            'region' => null,
            'zip_code' => null,
            'house_type' => 'Single',
            'lot_area' => null,
            'floor_area' => null,
            'year_built' => null,
            'ownership' => 'Owned',
            'num_members' => $this->faker->numberBetween(1, 8),
            'head_of_household_id' => null,
            'status' => 'Occupied',
            'remarks' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
