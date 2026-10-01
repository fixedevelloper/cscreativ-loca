<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'phone' => fake()->numerify('6########'),
            'role' => 'owner',
            'password' => static::$password ??= Hash::make('123456789'),
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => 'owner']);
    }

    public function agent(): static
    {
        return $this->state(['role' => 'agent']);
    }

    public function tenant(): static
    {
        return $this->state(['role' => 'tenant']);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
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
}
