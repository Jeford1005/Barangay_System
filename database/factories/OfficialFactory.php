<?php

namespace Database\Factories;

use App\Models\Official;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfficialFactory extends Factory
{
    protected $model = Official::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'middle_name' => null,
            'last_name' => $this->faker->lastName(),
            'suffix' => null,
            'birth_date' => $this->faker->date('Y-m-d', '1980-01-01'),
            'sex' => $this->faker->randomElement(['Male', 'Female', 'Other']),
            'office' => 'Barangay Hall',
            'position' => $this->faker->randomElement([
                'Punong Barangay', 'Barangay Kagawad', 'Barangay Secretary', 'Barangay Treasurer',
            ]),
            'barangay' => 'Barangay Bidduang',
            'municipality' => null,
            'province' => null,
            'region' => null,
            'zip_code' => null,
            'phone_number' => $this->faker->numerify('09#########'),
            'email' => null,
            'photo' => null,
            'sign_image' => null,
            'term_start' => '2023-01-01',
            'term_end' => '2026-12-31',
            'status' => 'Active',
        ];
    }
}
