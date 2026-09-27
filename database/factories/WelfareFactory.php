<?php

namespace Database\Factories;

use App\Models\Welfare;
use Illuminate\Database\Eloquent\Factories\Factory;

class WelfareFactory extends Factory
{
    protected $model = Welfare::class;

    public function definition(): array
    {
        return [
            'beneficiary_name' => $this->faker->name(),
            'beneficiary_address' => $this->faker->streetAddress(),
            'beneficiary_phone' => $this->faker->numerify('09#########'),
            'assistance_type' => $this->faker->randomElement(['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other']),
            'program_name' => $this->faker->randomElement([
                'Ayuda sa Pamilyang Pilipino', 'Senior Citizen Assistance',
                'Medical Assistance Program', 'Educational Financial Aid',
            ]),
            'program_description' => $this->faker->sentence(10),
            'requested_amount' => $this->faker->randomFloat(2, 500, 20000),
            'approved_amount' => 0,
            'status' => $this->faker->randomElement(['Requested', 'Under Review', 'Approved', 'Denied', 'Released']),
            'request_date' => $this->faker->date('Y-m-d', 'yesterday'),
            'remarks' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'Approved',
            'approved_amount' => $this->faker->randomFloat(2, 500, 15000),
            'approval_date' => now()->toDateString(),
        ]);
    }

    public function released(): static
    {
        return $this->state(fn () => [
            'status' => 'Released',
            'approved_amount' => $this->faker->randomFloat(2, 500, 15000),
            'approval_date' => now()->subDays(3)->toDateString(),
            'release_date' => now()->toDateString(),
        ]);
    }
}
