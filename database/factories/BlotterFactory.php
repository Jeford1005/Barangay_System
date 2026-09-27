<?php

namespace Database\Factories;

use App\Models\Blotter;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlotterFactory extends Factory
{
    protected $model = Blotter::class;

    public function definition(): array
    {
        return [
            'case_number' => 'BLTR-' . now()->format('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'complainant_name' => $this->faker->name(),
            'complainant_address' => $this->faker->streetAddress(),
            'complainant_phone' => $this->faker->numerify('09#########'),
            'accused_name' => $this->faker->name(),
            'accused_address' => $this->faker->streetAddress(),
            'accused_phone' => $this->faker->numerify('09#########'),
            'complaint_type' => $this->faker->randomElement([
                'Noise Complaint', 'Boundary Dispute', 'Physical Injury',
                'Theft', 'Verbal Dispute', 'Property Damage',
            ]),
            'complaint_date' => $this->faker->date('Y-m-d', 'yesterday'),
            'complaint_time' => $this->faker->optional()->time('H:i'),
            'alleged_offense' => $this->faker->sentence(12),
            'status' => $this->faker->randomElement(['Open', 'Pending', 'Resolved', 'Dismissed']),
            'arrest_made' => 'No',
            'investigator' => null,
            'officer_id' => null,
            'remarks' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'Open']);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => 'Resolved',
            'disposition' => 'Amicable settlement executed before the lupong tagapamayapa.',
            'disposition_date' => now()->toDateString(),
        ]);
    }
}
