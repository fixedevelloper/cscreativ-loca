<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rent;
use App\Models\Contract;
use Faker\Factory as Faker;

class RentSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $contracts = Contract::where('status', 'active')->get();

        foreach ($contracts as $contract) {
            for ($m = 1; $m <= 6; $m++) {
                Rent::create([
                    'contract_id' => $contract->id,
                    'month' => now()->subMonths($m)->format('Y-m'),
                    'due_date' => now()->subMonths($m)->day((int) config('rent.due_day'))->toDateString(),
                    'amount' => $contract->rent_amount,
                    'status' => $faker->randomElement(['paid', 'pending', 'late']),
                ]);
            }
        }
    }
}
