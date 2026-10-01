<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Souscription active à un plan du catalogue serveur (config/plans.php).
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return $this->planAttributes($this->faker->randomElement(array_keys(config('plans')))) + [
            'user_id' => User::factory()->owner(),
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ];
    }

    /** Souscription au plan $key (free, starter, pro, agency). */
    public function plan(string $key): static
    {
        return $this->state($this->planAttributes($key));
    }

    private function planAttributes(string $key): array
    {
        $plan = config("plans.{$key}");

        return [
            'plan_name' => $key,
            'price' => $plan['price'],
            'property_limit' => $plan['property_limit'],
            'tenant_limit' => $plan['tenant_limit'],
            'duration_days' => $plan['duration_days'],
            'end_date' => now()->addDays($plan['duration_days'])->toDateString(),
        ];
    }
}
