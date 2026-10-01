<?php

namespace Database\Factories;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Échéance de loyer d'un contrat. Le montant reprend celui du bail.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rent>
 */
class RentFactory extends Factory
{
    public function definition(): array
    {
        $month = now()->subMonths($this->faker->numberBetween(0, 11));

        return [
            'contract_id' => Contract::factory(),
            'month' => $month->format('Y-m'),
            'due_date' => $month->copy()->day((int) config('rent.due_day'))->toDateString(),
            'amount' => fn (array $attributes) => Contract::find($attributes['contract_id'])->rent_amount,
            'status' => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid']);
    }
}
