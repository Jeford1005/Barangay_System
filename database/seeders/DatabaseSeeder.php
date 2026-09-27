<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Household;
use App\Models\Official;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Every row is keyed on a natural key
     * (firstOrCreate / updateOrCreate) so `php artisan db:seed` is re-runnable.
     *
     * Demo credentials, all with password "password":
     *   admin@barangay.local    — administrator
     *   staff@barangay.local    — barangay staff
     *   resident@barangay.local — resident (matched to the demo resident below)
     */
    public function run(): void
    {
        /* ------------------------------- accounts ------------------------------ */

        User::updateOrCreate(
            ['email' => 'admin@barangay.local'],
            [
                'name' => 'Barangay Administrator',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'approved_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'staff@barangay.local'],
            [
                'name' => 'Barangay Staff',
                'password' => 'password',
                'role' => User::ROLE_STAFF,
                'status' => User::STATUS_ACTIVE,
                'approved_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'resident@barangay.local'],
            [
                'name' => 'Juan Santos Dela Cruz',
                'password' => 'password',
                'role' => User::ROLE_RESIDENT,
                'status' => User::STATUS_ACTIVE,
                'approved_at' => now(),
            ],
        );

        /* -------------------------------- puroks ------------------------------- */

        $puroks = collect([
            ['name' => 'Purok 1', 'code' => 'P1', 'description' => 'Barangay center / Barangay Hall area'],
            ['name' => 'Purok 2', 'code' => 'P2', 'description' => 'North residential area'],
            ['name' => 'Purok 3', 'code' => 'P3', 'description' => 'Farm road area'],
            ['name' => 'Purok 4', 'code' => 'P4', 'description' => 'Riverside area'],
            ['name' => 'Purok 5', 'code' => 'P5', 'description' => 'South / highway area'],
        ])->mapWithKeys(fn (array $purok) => [
            $purok['code'] => Purok::firstOrCreate(['code' => $purok['code']], $purok),
        ]);

        /* -------------------------- certificate catalog ------------------------ */

        Document::updateOrCreate(['code' => 'CLR'], [
            'title' => 'Barangay Clearance',
            'description' => 'Certifies the resident is of good moral character with no pending case.',
            'document_type' => 'Clearance',
            'fee' => 50,
            'status' => 'Active',
        ]);

        Document::updateOrCreate(['code' => 'COR'], [
            'title' => 'Certificate of Residency',
            'description' => 'Certifies the resident is a bona fide resident of the barangay.',
            'document_type' => 'Certificate',
            'fee' => 30,
            'status' => 'Active',
        ]);

        Document::updateOrCreate(['code' => 'IND'], [
            'title' => 'Certificate of Indigency',
            'description' => 'Certifies the resident belongs to an indigent family.',
            'document_type' => 'Certificate',
            'fee' => 0,
            'status' => 'Active',
        ]);

        /* ------------------------------- officials ----------------------------- */

        Official::updateOrCreate(
            ['full_name' => 'Jose Ramos Fernandez', 'position' => 'Punong Barangay'],
            [
                'term_start' => '2023-01-01',
                'term_end' => '2026-12-31',
                'contact' => '0917-000-0001',
                'status' => Official::STATUS_ACTIVE,
            ],
        );

        Official::updateOrCreate(
            ['full_name' => 'Anna Cruz Torres', 'position' => 'Barangay Kagawad'],
            [
                'term_start' => '2023-01-01',
                'term_end' => '2026-12-31',
                'contact' => '0917-000-0002',
                'status' => Official::STATUS_ACTIVE,
            ],
        );

        /* ------------------------------ households ----------------------------- */

        $households = collect([
            ['household_number' => 'HH-001', 'address' => '12 Main Street', 'purok' => 'P1', 'house_type' => 'Single', 'ownership' => 'Owned'],
            ['household_number' => 'HH-002', 'address' => '34 Second Street', 'purok' => 'P1', 'house_type' => 'Duplex', 'ownership' => 'Rented'],
            ['household_number' => 'HH-003', 'address' => '56 Third Street', 'purok' => 'P2', 'house_type' => 'Single', 'ownership' => 'Owned'],
        ])->mapWithKeys(fn (array $h) => [
            $h['household_number'] => Household::firstOrCreate(
                ['household_number' => $h['household_number']],
                [
                    'address' => $h['address'],
                    'purok_id' => $puroks[$h['purok']]->id,
                    'house_type' => $h['house_type'],
                    'ownership' => $h['ownership'],
                    'status' => 'Occupied',
                    'member_count' => 0,
                ],
            ),
        ]);

        /* -------------------------------- residents ---------------------------- */

        $residents = collect([
            [
                'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz',
                'birth_date' => '1985-04-12', 'sex' => 'Male', 'civil_status' => 'Married',
                'occupation' => 'Farmer', 'phone' => '0917-123-4567',
                'email' => 'resident@barangay.local', 'address' => '12 Main Street',
                'purok' => 'P1', 'household' => 'HH-001',
            ],
            [
                'first_name' => 'Maria', 'middle_name' => 'Garcia', 'last_name' => 'Dela Cruz',
                'birth_date' => '1990-09-23', 'sex' => 'Female', 'civil_status' => 'Married',
                'occupation' => 'Storekeeper', 'phone' => '0917-123-4568',
                'email' => 'maria.delacruz@example.com', 'address' => '12 Main Street',
                'purok' => 'P1', 'household' => 'HH-001',
            ],
            [
                'first_name' => 'Pedro', 'middle_name' => 'Reyes', 'last_name' => 'Santos',
                'birth_date' => '1978-01-05', 'sex' => 'Male', 'civil_status' => 'Widowed',
                'occupation' => 'Driver', 'phone' => '0917-123-4569',
                'email' => 'pedro.santos@example.com', 'address' => '34 Second Street',
                'purok' => 'P1', 'household' => 'HH-002',
            ],
        ])->mapWithKeys(fn (array $r) => [
            $r['email'] => Resident::firstOrCreate(
                ['email' => $r['email']],
                [
                    'first_name' => $r['first_name'],
                    'middle_name' => $r['middle_name'],
                    'last_name' => $r['last_name'],
                    'birth_date' => $r['birth_date'],
                    'sex' => $r['sex'],
                    'civil_status' => $r['civil_status'],
                    'occupation' => $r['occupation'],
                    'phone' => $r['phone'],
                    'address' => $r['address'],
                    'purok_id' => $puroks[$r['purok']]->id,
                    'household_id' => $households[$r['household']]->id,
                    'status' => Resident::STATUS_ACTIVE,
                ],
            ),
        ]);

        /* --------------------- household heads + member counts ------------------ */

        $households['HH-001']->forceFill(['head_resident_id' => $residents['resident@barangay.local']->id])->save();
        $households['HH-002']->forceFill(['head_resident_id' => $residents['pedro.santos@example.com']->id])->save();

        $households->each(fn (Household $household) => $household->syncMemberCount());

        // The demo resident account now points at its profile (also lazily
        // resolved by User::linkedResident() through the matching email).
        User::where('email', 'resident@barangay.local')->first()
            ?->forceFill(['resident_id' => $residents['resident@barangay.local']->id])
            ->save();
    }
}
