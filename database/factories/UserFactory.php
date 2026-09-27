<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Default: an office account that is allowed in — most tests and
            // fixtures want a usable user. Pending/resident states are opt-in.
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'approved_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function resident(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_RESIDENT,
        ]);
    }

    /** A self-registered account still waiting for the administrator. */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_PENDING,
            'approved_at' => null,
            'reviewed_by' => null,
        ]);
    }

    /** A suspended account — cannot sign in to the system. */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => 'Policy violation',
        ]);
    }
}
