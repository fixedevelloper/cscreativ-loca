<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payment;
use App\Models\Rent;
use Faker\Factory as Faker;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $rents = Rent::where('status', 'paid')->with('contract.property')->get();

        foreach ($rents as $rent) {
            Payment::create([
                'rent_id' => $rent->id,
                'user_id' => $rent->contract->property->user_id, // encaissé par le bailleur
                'amount' => $rent->amount,
                'method' => $faker->randomElement(['cash', 'bank', 'mobile']),
                'status' => 'success',
            ]);
        }
    }
}

