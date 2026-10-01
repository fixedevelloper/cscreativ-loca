<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PropertyTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Conventions transverses : format des listes et des erreurs (voir
 * App\Http\Controllers\Controller), factories et seeders alignés sur le
 * schéma, mode debug jamais exposé en production.
 */
class ApiConventionsTest extends TestCase
{
    use RefreshDatabase;

    public static function listEndpoints(): array
    {
        return array_map(fn ($uri) => [$uri], [
            'properties' => '/api/properties',
            'tenants' => '/api/tenants',
            'contracts' => '/api/contracts',
            'rents' => '/api/rents',
            'payments' => '/api/payments',
            'documents' => '/api/documents',
            'inventories' => '/api/inventories',
        ]);
    }

    #[DataProvider('listEndpoints')]
    public function test_every_list_uses_the_common_paginated_format(string $uri): void
    {
        $payment = Payment::factory()->create();
        $owner = $payment->rent->contract->property->owner;
        Subscription::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->getJson("{$uri}?limit=500&page=1")
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'page', 'limit', 'totalPages'])
            ->assertJsonPath('limit', 100); // plafonné
    }

    public function test_admin_user_list_uses_the_common_format_and_filters(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->agent()->create();
        User::factory()->owner()->create(['name' => 'Bailleur Recherché']);

        $this->actingAs($admin)->getJson('/api/users?role=agent&limit=2')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'page', 'limit', 'totalPages'])
            ->assertJsonPath('total', 3)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.password');

        $this->actingAs($admin)->getJson('/api/users?search=Recherch')
            ->assertOk()->assertJsonPath('total', 1);
    }

    public function test_rent_list_exposes_tenant_and_property_names(): void
    {
        $payment = Payment::factory()->create();
        $contract = $payment->rent->contract;
        $owner = $contract->property->owner;
        Subscription::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->getJson('/api/rents')
            ->assertOk()
            ->assertJsonPath('data.0.tenant_name', $contract->tenant->user->name)
            ->assertJsonPath('data.0.property_name', $contract->property->name);

        $this->actingAs($owner)->getJson('/api/payments')
            ->assertOk()
            ->assertJsonPath('data.0.property_name', $contract->property->name)
            ->assertJsonPath('data.0.owner_name', $owner->name);
    }

    public function test_errors_use_the_common_format(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->getJson('/api/properties')
            ->assertForbidden()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'subscription_required')
            ->assertJsonStructure(['message']);

        $this->actingAs($owner)->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['message']);
    }

    public function test_debug_details_are_never_exposed_in_production(): void
    {
        config(['app.debug' => true]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->getJson('/api/properties')
            ->assertUnauthorized()
            ->assertJsonPath('debug', null);
    }

    public function test_factories_build_a_consistent_rental_graph(): void
    {
        $payment = Payment::factory()->create();
        $rent = $payment->rent;
        $contract = $rent->contract;

        $this->assertSame('tenant', $contract->tenant->user->role);
        $this->assertSame('owner', $contract->property->owner->role);
        $this->assertSame($contract->tenant->property_id, $contract->property_id);
        $this->assertEquals($contract->rent_amount, $rent->amount);
        $this->assertEquals($rent->amount, $payment->amount);
        $this->assertArrayHasKey(Subscription::factory()->create()->plan_name, config('plans'));
    }

    public function test_seeders_run_and_produce_coherent_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PropertyTenantSeeder::class);

        $this->assertGreaterThan(0, Contract::count());
        // Un locataire n'est jamais le compte du bailleur.
        $this->assertSame(0, Tenant::whereHas('user', fn ($q) => $q->where('role', '!=', 'tenant'))->count());
        // Tous les plans existent au catalogue serveur.
        $this->assertSame([], Subscription::pluck('plan_name')->diff(array_keys(config('plans')))->values()->all());
        // Chaque bailleur a un abonnement actif ; les agents sont rattachés.
        User::where('role', 'owner')->each(fn ($owner) => $this->assertNotNull($owner->activeSubscription()));
        $this->assertGreaterThan(0, \DB::table('agent_owner')->count());
    }
}
