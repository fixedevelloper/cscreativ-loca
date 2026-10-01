<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bail actif entre un locataire et le bien qu'il occupe (property_id repris
 * de la fiche locataire pour rester cohérent).
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'property_id' => fn (array $attributes) => Tenant::find($attributes['tenant_id'])->property_id,
            'start_date' => $this->faker->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'end_date' => null,
            'number_person' => $this->faker->numberBetween(1, 5),
            'rent_amount' => $this->faker->numberBetween(50000, 300000),
            'deposit_amount' => $this->faker->numberBetween(0, 600000),
            'terms' => $this->faker->sentence(10),
            'status' => 'active',
        ];
    }

    public function ended(): static
    {
        return $this->state([
            'end_date' => now()->subMonth()->toDateString(),
            'status' => 'terminated',
        ]);
    }
}
