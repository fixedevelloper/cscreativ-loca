<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/**
 * Matrice d'accès par rôle : chaque liste ne renvoie que les données du
 * périmètre de l'utilisateur, et les écritures sont bornées par les policies.
 * Deux bailleurs A et B ; un agent qui ne gère que A ; un locataire de A.
 */
class RoleAccessTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private User $agentA;
    private User $tenantA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->user('owner');
        $this->ownerB = $this->user('owner');
        $this->subscribe($this->ownerA);
        $this->subscribe($this->ownerB);

        [$contractA, $this->tenantA] = $this->lease($this->ownerA, 'Bien A');
        [$contractB] = $this->lease($this->ownerB, 'Bien B');

        $this->rentWithPayment($contractA);
        $this->rentWithPayment($contractB);

        foreach ([[$contractA, $this->ownerA], [$contractB, $this->ownerB]] as [$contract, $owner]) {
            Inventory::create([
                'property_id' => $contract->property_id,
                'contract_id' => $contract->id,
                'user_id' => $owner->id,
                'type' => 'entry',
                'inventory_date' => now(),
            ]);
        }

        $this->agentA = $this->user('agent');
        $this->agentA->owners()->attach($this->ownerA->id);
    }

    /** Nombre d'éléments renvoyés par une liste, quel que soit son format. */
    private function listCount(User $user, string $uri): int
    {
        $json = $this->actingAs($user)->getJson($uri)->assertOk()->json();

        return $json['total'];
    }

    public static function listEndpoints(): array
    {
        return [
            'properties' => ['/api/properties'],
            'tenants' => ['/api/tenants'],
            'contracts' => ['/api/contracts'],
            'rents' => ['/api/rents'],
            'payments' => ['/api/payments'],
            'inventories' => ['/api/inventories'],
        ];
    }

    #[DataProvider('listEndpoints')]
    public function test_owner_and_agent_only_list_their_scope(string $uri): void
    {
        $this->assertSame(1, $this->listCount($this->ownerA, $uri));
        $this->assertSame(1, $this->listCount($this->agentA, $uri));
        $this->assertSame(1, $this->listCount($this->ownerB, $uri));
    }

    #[DataProvider('listEndpoints')]
    public function test_tenant_only_lists_own_lease_data(string $uri): void
    {
        // Les biens ne sont pas visibles d'un locataire (scope Property).
        $expected = $uri === '/api/properties' ? 0 : 1;

        $this->assertSame($expected, $this->listCount($this->tenantA, $uri));
    }

    public function test_tenant_cannot_write_landlord_resources(): void
    {
        $property = $this->ownerA->properties()->first();
        $rent = \App\Models\Rent::whereHas('contract', fn ($q) => $q->where('property_id', $property->id))->first();

        $this->actingAs($this->tenantA)->postJson('/api/properties', [
            'name' => 'x', 'address' => 'x', 'city' => 'x', 'type' => 'studio',
            'surface' => '1', 'number_piece' => 1, 'monthly_rent' => 1,
        ])->assertForbidden();

        $this->actingAs($this->tenantA)->postJson('/api/payments', [
            'rent_id' => $rent->id, 'amount' => 10, 'method' => 'cash',
        ])->assertForbidden();
    }

    public function test_owner_cannot_record_payment_on_another_owners_rent(): void
    {
        $rentB = \App\Models\Rent::whereHas('contract.property', fn ($q) => $q->where('user_id', $this->ownerB->id))->first();

        $this->actingAs($this->ownerA)->postJson('/api/payments', [
            'rent_id' => $rentB->id, 'amount' => 10, 'method' => 'cash',
        ])->assertForbidden();
    }

    public function test_contract_sub_resources_are_scoped(): void
    {
        $contractB = \App\Models\Contract::whereHas('property', fn ($q) => $q->where('user_id', $this->ownerB->id))->first();

        $this->actingAs($this->ownerA)->getJson("/api/rents/{$contractB->id}/contract")->assertForbidden();
        $this->actingAs($this->ownerA)->getJson("/api/payments/{$contractB->id}/contract")->assertForbidden();
        $this->actingAs($this->agentA)->getJson("/api/payments/{$contractB->id}/contract")->assertForbidden();
    }

    public function test_admin_routes_are_reserved_to_admins(): void
    {
        $this->actingAs($this->ownerA)->getJson('/api/users')->assertForbidden();
        $this->actingAs($this->agentA)->postJson('/api/users/staff', [])->assertForbidden();
        $this->actingAs($this->user('admin'))->getJson('/api/users')->assertOk();
    }

    public function test_agent_cannot_read_owners_list_of_another_agent(): void
    {
        $otherAgent = $this->user('agent');

        $this->actingAs($this->agentA)->getJson("/api/agents/{$this->agentA->id}/owners")->assertOk();
        $this->actingAs($this->agentA)->getJson("/api/agents/{$otherAgent->id}/owners")->assertForbidden();
    }
}
