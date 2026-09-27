<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ResidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'middle_name' => null,
            'last_name' => $this->faker->lastName(),
            'suffix' => null,
            'birth_date' => $this->faker->date('Y-m-d', '2005-01-01'),
            'birthplace' => null,
            'sex' => $this->faker->randomElement(['Male', 'Female']),
            'civil_status' => 'Single',
            'nationality' => 'Filipino',
            'religion' => null,
            'education_level' => null,
            'occupation' => null,
            'spouse_name' => null,
            'blood_type' => null,
            'phone_number' => null,
            'email' => null,
            'address' => null,
            'residency_status' => null,
            'voter_status' => false,
            'is_household_head' => false,
            'status' => 'Active',
            'purok_id' => null,
            'household_id' => null,
            'user_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
