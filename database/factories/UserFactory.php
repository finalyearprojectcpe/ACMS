<?php

namespace Database\Factories;

use App\AccountType;
use App\Models\School;
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
            'account_type' => AccountType::Student,
            'school_id' => School::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'access_code' => null,
            'access_code_used_at' => null,
            'activated_at' => now(),
            'remember_token' => Str::random(10),
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

    /**
     * Indicate that the user is a student.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Student,
        ]);
    }

    /**
     * Indicate that the user manages school-level setup.
     */
    public function schoolAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::SchoolAdmin,
        ]);
    }

    /**
     * Indicate that the user is a staff member.
     */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Staff,
        ]);
    }

    public function pendingActivation(string $accessCode = 'ACCESS123'): static
    {
        return $this->state(fn (array $attributes) => [
            'access_code' => $accessCode,
            'access_code_used_at' => null,
            'activated_at' => null,
            'password' => Hash::make(Str::random(32)),
        ]);
    }
}
