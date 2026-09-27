<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Demo credentials, all with password "password":
     *   admin@barangay.local  — administrator
     *   staff@barangay.local  — barangay staff
     *   resident@barangay.local — resident (approved)
     */
    public function run(): void
    {
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
                'name' => 'Juan Dela Cruz',
                'password' => 'password',
                'role' => User::ROLE_RESIDENT,
                'status' => User::STATUS_ACTIVE,
                'approved_at' => now(),
            ],
        );
    }
}
