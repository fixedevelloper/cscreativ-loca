<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Un locataire pour environ deux biens sur trois. Chaque locataire a son
 * propre compte utilisateur (rôle tenant) : avant, tenants.user_id pointait
 * sur le BAILLEUR, ce qui faisait de lui son propre locataire et faussait
 * tous les filtres d'accès par rôle.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Property::all()->each(function (Property $property) {
            if (fake()->boolean(66)) {
                Tenant::factory()->create([
                    'user_id' => User::factory()->tenant(),
                    'property_id' => $property->id,
                ]);
                $property->update(['status' => 'occupied']);
            }
        });
    }
}
