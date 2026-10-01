<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Rent;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

/**
 * Jeu de données autonome et rapide, construit uniquement par factories
 * (php artisan db:seed --class=PropertyTenantSeeder). Chaque factory crée
 * ses dépendances (bailleur, bien, locataire, bail) de façon cohérente.
 */
class PropertyTenantSeeder extends Seeder
{
    public function run(): void
    {
        // Loyers soldés avec leur encaissement (crée bail, locataire, bien, bailleur).
        Payment::factory()->count(10)->create();

        // Loyers en attente.
        Rent::factory()->count(10)->create();

        // Un abonnement actif pour chaque bailleur créé.
        \App\Models\User::where('role', 'owner')->doesntHave('subscriptions')->each(
            fn ($owner) => Subscription::factory()->create(['user_id' => $owner->id])
        );
    }
}
