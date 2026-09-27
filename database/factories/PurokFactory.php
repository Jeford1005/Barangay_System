<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PurokFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Purok '.$this->faker->unique()->numberBetween(1, 999),
            'code' => null,
            'created_by' => null,
        ];
    }
}
