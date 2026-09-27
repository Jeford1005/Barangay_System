<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            // Distinct from the seeded catalog codes (CLR/COR/IND).
            'code' => 'TST'.$this->faker->unique()->numberBetween(100, 999),
            'title' => $this->faker->randomElement(['Test Certificate A', 'Test Certificate B', 'Test Certificate C']),
            'description' => $this->faker->sentence(),
            'category' => 'Certificate',
            'document_type' => 'Certificate',
            'requirements' => "Valid ID\nCommunity Tax Certificate",
            'fee' => $this->faker->randomElement([0, 30, 50]),
            'status' => 'Active',
        ];
    }
}
