<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->owner(),
            'name' => $this->faker->randomElement(['Appartement', 'Studio', 'Villa', 'Chambre']) . ' ' . $this->faker->streetName(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->randomElement(['Douala', 'Yaoundé', 'Bafoussam', 'Kribi']),
            'surface' => $this->faker->numberBetween(15, 200) . ' m²',
            'number_piece' => $this->faker->numberBetween(1, 6),
            'monthly_charge' => $this->faker->numberBetween(0, 20000),
            'type' => $this->faker->randomElement(['room', 'studio', 'apartment', 'office', 'other']),
            'monthly_rent' => $this->faker->numberBetween(50000, 300000),
        ];
    }

    public function occupied(): static
    {
        return $this->state(['status' => 'occupied']);
    }
}
