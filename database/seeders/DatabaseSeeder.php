<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\Official;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The run is wrapped in a transaction and keyed on natural keys, so
     * re-running `db:seed` updates the sample rows instead of aborting partway
     * with a unique-constraint error and a half-applied dataset.
     */
    public function run(): void
    {
        // Seeders run outside HTTP validation, so unguard mass assignment:
        // several models deliberately exclude privileged keys (role, user_id,
        // head id) from $fillable. Laravel unguards seeders via Model::unguard
        // in newer versions; do it explicitly for safety.
        Model::unguard();
        try {
            DB::transaction(function (): void {
                $this->seed();
            });
        } finally {
            Model::reguard();
        }
    }

    private function seed(): void
    {
        // Create default admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@barangay.local'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('barangay-2026'),
                'email_verified_at' => now(),
                'user_type' => 'admin',
                'status' => 'approved',
            ],
        );

        // Create staff user. Existing installations are not changed by this
        // seeder; administrators can explicitly review and assign the role.
        $staff = User::firstOrCreate(
            ['email' => 'staff@barangay.local'],
            [
                'name' => 'Staff User',
                'password' => Hash::make('barangay-2026'),
                'email_verified_at' => now(),
                'user_type' => 'staff',
                'status' => 'approved',
            ],
        );

        // Barangay officials sign in on the same office tab as staff. They
        // review and decide the approval queues and record blotter cases, but
        // they never enter clerical records and never delete anything.
        User::firstOrCreate(
            ['email' => 'official@barangay.local'],
            [
                'name' => 'Official User',
                'password' => Hash::make('barangay-2026'),
                'email_verified_at' => now(),
                'user_type' => 'official',
                'status' => 'approved',
            ],
        );

        // Create sample puroks
        $puroks = [
            ['name' => 'Purok 1', 'code' => 'P1', 'created_by' => $admin->id],
            ['name' => 'Purok 2', 'code' => 'P2', 'created_by' => $admin->id],
            ['name' => 'Purok 3', 'code' => 'P3', 'created_by' => $admin->id],
            ['name' => 'Purok 4', 'code' => 'P4', 'created_by' => $admin->id],
            ['name' => 'Purok 5', 'code' => 'P5', 'created_by' => $admin->id],
            ['name' => 'Purok 6', 'code' => 'P6', 'created_by' => $admin->id],
        ];

        foreach ($puroks as $purokData) {
            Purok::firstOrCreate(['code' => $purokData['code']], $purokData);
        }

        // Create sample households
        $purok1 = Purok::where('code', 'P1')->first();
        $purok2 = Purok::where('code', 'P2')->first();

        $households = [
            [
                'household_code' => 'HH-001',
                'purok_id' => $purok1->id,
                'street' => 'Main Street',
                'barangay' => 'Sample Barangay',
                'municipality' => 'Sample Municipality',
                'province' => 'Sample Province',
                'house_type' => 'Single',
                'ownership' => 'Owned',
                'num_members' => 4,
                'status' => 'Occupied',
                'created_by' => $admin->id,
            ],
            [
                'household_code' => 'HH-002',
                'purok_id' => $purok1->id,
                'street' => 'Second Street',
                'barangay' => 'Sample Barangay',
                'municipality' => 'Sample Municipality',
                'province' => 'Sample Province',
                'house_type' => 'Duplex',
                'ownership' => 'Rented',
                'num_members' => 3,
                'status' => 'Occupied',
                'created_by' => $admin->id,
            ],
            [
                'household_code' => 'HH-003',
                'purok_id' => $purok2->id,
                'street' => 'Third Street',
                'barangay' => 'Sample Barangay',
                'municipality' => 'Sample Municipality',
                'province' => 'Sample Province',
                'house_type' => 'Single',
                'ownership' => 'Owned',
                'num_members' => 5,
                'status' => 'Occupied',
                'created_by' => $admin->id,
            ],
        ];

        foreach ($households as $householdData) {
            Household::firstOrCreate(
                ['household_code' => $householdData['household_code']],
                $householdData,
            );
        }

        // Create sample residents
        $household1 = Household::where('household_code', 'HH-001')->first();
        $household2 = Household::where('household_code', 'HH-002')->first();

        $residents = [
            [
                'first_name' => 'Juan',
                'middle_name' => 'Santos',
                'last_name' => 'Dela Cruz',
                'birth_date' => '1985-05-15',
                'birthplace' => 'Manila',
                'sex' => 'Male',
                'civil_status' => 'Married',
                'nationality' => 'Filipino',
                'religion' => 'Roman Catholic',
                'occupation' => 'Teacher',
                'phone_number' => '09171234567',
                'email' => 'juan.delacruz@example.com',
                'address' => 'Main Street, Sample Barangay',
                'voter_status' => true,
                'is_household_head' => true,
                'status' => 'Active',
                'purok_id' => $purok1->id,
                'household_id' => $household1->id,
                'user_id' => null,
                'created_by' => $admin->id,
            ],
            [
                'first_name' => 'Maria',
                'middle_name' => 'Garcia',
                'last_name' => 'Dela Cruz',
                'birth_date' => '1987-08-20',
                'birthplace' => 'Quezon City',
                'sex' => 'Female',
                'civil_status' => 'Married',
                'nationality' => 'Filipino',
                'religion' => 'Roman Catholic',
                'occupation' => 'Nurse',
                'phone_number' => '09181234567',
                'email' => 'maria.delacruz@example.com',
                'address' => 'Main Street, Sample Barangay',
                'voter_status' => true,
                'is_household_head' => false,
                'status' => 'Active',
                'purok_id' => $purok1->id,
                'household_id' => $household1->id,
                'user_id' => null,
                'created_by' => $admin->id,
            ],
            [
                'first_name' => 'Pedro',
                'middle_name' => 'Reyes',
                'last_name' => 'Santos',
                'birth_date' => '1990-03-10',
                'birthplace' => 'Cebu',
                'sex' => 'Male',
                'civil_status' => 'Single',
                'nationality' => 'Filipino',
                'religion' => 'Roman Catholic',
                'occupation' => 'Engineer',
                'phone_number' => '09191234567',
                'email' => 'pedro.santos@example.com',
                'address' => 'Second Street, Sample Barangay',
                'voter_status' => true,
                'is_household_head' => true,
                'status' => 'Active',
                'purok_id' => $purok1->id,
                'household_id' => $household2->id,
                'user_id' => null,
                'created_by' => $staff->id,
            ],
        ];

        foreach ($residents as $residentData) {
            Resident::firstOrCreate(['email' => $residentData['email']], $residentData);
        }

        // Update household heads
        $resident1 = Resident::where('email', 'juan.delacruz@example.com')->first();
        $household1->update(['head_of_household_id' => $resident1->id]);

        $resident3 = Resident::where('email', 'pedro.santos@example.com')->first();
        $household2->update(['head_of_household_id' => $resident3->id]);

        // Create sample barangay officials
        $officials = [
            [
                'first_name' => 'Jose',
                'middle_name' => 'Ramos',
                'last_name' => 'Fernandez',
                'birth_date' => '1975-06-15',
                'sex' => 'Male',
                'office' => 'Barangay Hall',
                'position' => 'Barangay Captain',
                'barangay' => 'Sample Barangay',
                'municipality' => 'Sample Municipality',
                'province' => 'Sample Province',
                'phone_number' => '09201234567',
                'email' => 'captain@barangay.local',
                'term_start' => '2023-01-01',
                'term_end' => '2026-12-31',
                'status' => 'Active',
                'created_by' => $admin->id,
            ],
            [
                'first_name' => 'Anna',
                'middle_name' => 'Cruz',
                'last_name' => 'Torres',
                'birth_date' => '1980-09-22',
                'sex' => 'Female',
                'office' => 'Barangay Hall',
                'position' => 'Barangay Kagawad',
                'barangay' => 'Sample Barangay',
                'municipality' => 'Sample Municipality',
                'province' => 'Sample Province',
                'phone_number' => '09211234567',
                'email' => 'kagawad1@barangay.local',
                'term_start' => '2023-01-01',
                'term_end' => '2026-12-31',
                'status' => 'Active',
                'created_by' => $admin->id,
            ],
        ];

        foreach ($officials as $officialData) {
            Official::firstOrCreate(['email' => $officialData['email']], $officialData);
        }

        // Demo resident account: the portal resolves everything through
        // user->residentProfile (residents.user_id), so the account must be
        // linked to a resident row or the portal renders empty.
        $resident = User::firstOrCreate(
            ['email' => 'resident@barangay.local'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make('barangay-2026'),
                'email_verified_at' => now(),
                'user_type' => 'resident',
                'status' => 'approved',
            ],
        );

        $demoResident = Resident::where('email', 'juan.delacruz@example.com')->first();
        if ($demoResident && $demoResident->user_id !== $resident->id) {
            $demoResident->update(['user_id' => $resident->id]);
        }
    }
}
