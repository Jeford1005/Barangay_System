<?php

namespace Database\Factories;

use App\Models\CertificateIssuance;
use App\Models\Document;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateIssuanceFactory extends Factory
{
    protected $model = CertificateIssuance::class;

    public function definition(): array
    {
        $documentCode = 'T'.str_pad((string) $this->faker->unique()->numberBetween(100, 999), 3, '0', STR_PAD_LEFT);
        $sequence = str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'control_number' => $documentCode.'-'.now()->format('Y').'-'.$sequence,
            'document_id' => Document::factory()->state(['code' => $documentCode]),
            'resident_id' => Resident::factory(),
            'purpose' => $this->faker->randomElement(['Local employment', 'School requirement', 'Medical assistance', 'Bank requirement']),
            'copies' => 1,
            'fee' => 50.00,
            'status' => 'Issued',
        ];
    }
}
