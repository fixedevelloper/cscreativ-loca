<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Une souscription active par bailleur, à un plan du catalogue serveur
 * (config/plans.php). Les agents sont couverts par l'abonnement des bailleurs
 * qu'ils gèrent et l'admin n'en a pas besoin (voir SubscriptionMiddleware).
 */
class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        User::where('role', 'owner')->each(function (User $owner) {
            Subscription::factory()
                ->plan(fake()->randomElement(['pro', 'agency']))
                ->create(['user_id' => $owner->id]);
        });
    }
}
