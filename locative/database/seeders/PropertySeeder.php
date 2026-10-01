<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\User;
use Faker\Factory as Faker;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $owners = User::where('role', 'owner')->get();

        foreach ($owners as $owner) {
            for ($i = 0; $i < rand(2, 4); $i++) {
                Property::create([
                    'user_id' => $owner->id,
                    'name' => 'Propriété ' . $faker->word,
                    'address' => $faker->address,
                    'city' => $faker->city,
                    'surface' => $faker->numberBetween(30, 200) . ' m²',
                    'number_piece' => $faker->numberBetween(1, 5),
                    'monthly_charge' => $faker->randomFloat(2, 20, 100),
                    'type' => $faker->randomElement(['room', 'studio', 'apartment', 'office']),
                    'monthly_rent' => $faker->randomFloat(2, 100, 1000),
                ]);
            }
        }
    }
}
