<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fiche locataire. user_id référence le COMPTE du locataire (rôle tenant),
 * jamais celui du bailleur (le bailleur est property.user_id).
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->tenant(),
            'property_id' => Property::factory()->occupied(),
            'profession' => $this->faker->jobTitle(),
            'nationality' => 'Camerounaise',
            'gender' => $this->faker->randomElement(['male', 'female']),
            'date_birth' => $this->faker->date('Y-m-d', '-20 years'),
            'father_name' => $this->faker->firstNameMale(),
            'mother_name' => $this->faker->firstNameFemale(),
            'document_type' => $this->faker->randomElement(['cni', 'passport']),
            'document_id' => strtoupper($this->faker->bothify('??######')),
        ];
    }
}
