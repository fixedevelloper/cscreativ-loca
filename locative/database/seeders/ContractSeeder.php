<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Un bail actif par locataire, commencé assez tôt pour que RentSeeder ait
 * six mois d'échéances à créer. Montant = loyer affiché du bien.
 */
class ContractSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::with('property')->each(function (Tenant $tenant) {
            Contract::factory()->create([
                'tenant_id' => $tenant->id,
                'property_id' => $tenant->property_id,
                'start_date' => now()->subMonths(fake()->numberBetween(7, 24))->startOfMonth()->toDateString(),
                'rent_amount' => $tenant->property->monthly_rent,
            ]);
        });
    }
}
