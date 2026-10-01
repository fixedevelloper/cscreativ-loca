<?php

namespace Database\Factories;

use App\Models\Rent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Encaissement soldant un loyer (montant = montant du loyer).
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rent_id' => Rent::factory()->paid(),
            'amount' => fn (array $attributes) => Rent::find($attributes['rent_id'])->amount,
            'method' => $this->faker->randomElement(['cash', 'bank', 'mobile']),
            'status' => 'success',
        ];
    }
}
