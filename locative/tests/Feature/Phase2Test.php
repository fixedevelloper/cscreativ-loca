<?php

namespace Tests\Feature;

use App\Jobs\GenerateRentReceiptPdf;
use App\Models\Rent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/**
 * Phase 2 (AUDIT.txt §11) : création de biens par l'agent/admin, loyers
 * enrichis pour l'encaissement (reste dû, quittance), filtre des retards.
 */
class Phase2Test extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    private array $property = [
        'name' => 'Bien', 'address' => 'a', 'city' => 'c', 'type' => 'studio',
        'surface' => '10', 'number_piece' => 1, 'monthly_rent' => 1000,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_agent_creates_property_only_for_managed_owners(): void
    {
        $managed = $this->user('owner');
        $this->subscribe($managed);
        $other = $this->user('owner');
        $this->subscribe($other);
        $agent = $this->user('agent');
        $agent->owners()->attach($managed->id);

        $this->actingAs($agent)->postJson('/api/properties', $this->property)
            ->assertStatus(422)->assertJsonValidationErrors('owner_id');

        $this->actingAs($agent)->postJson('/api/properties', $this->property + ['owner_id' => $other->id])
            ->assertForbidden();

        $this->actingAs($agent)->postJson('/api/properties', $this->property + ['owner_id' => $managed->id])
            ->assertOk()->assertJsonPath('data.owner.id', $managed->id)->assertJsonPath('data.status', 'free');
    }

    public function test_owner_cannot_create_property_on_behalf_of_someone_else(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        $victim = $this->user('owner');

        $this->actingAs($owner)->postJson('/api/properties', $this->property + ['owner_id' => $victim->id])
            ->assertOk()->assertJsonPath('data.owner.id', $owner->id); // owner_id ignoré
        $this->assertSame(0, $victim->properties()->count());
    }

    public function test_agent_is_bound_by_the_owners_plan_limit(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner)->update(['property_limit' => 1]);
        $this->property($owner);
        $agent = $this->user('agent');
        $agent->owners()->attach($owner->id);

        $this->actingAs($agent)->postJson('/api/properties', $this->property + ['owner_id' => $owner->id])
            ->assertForbidden()->assertJsonPath('message', 'Limite de biens atteinte pour le plan du bailleur.');
    }

    public function test_admin_creates_property_for_any_owner_without_subscription_check(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($this->user('admin'))->postJson('/api/properties', $this->property + ['owner_id' => $owner->id])
            ->assertOk();
        $this->assertSame(1, $owner->properties()->count());
    }

    public function test_rents_expose_remaining_amount_and_receipt(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [$contract] = $this->lease($owner);
        $rent = Rent::create([
            'contract_id' => $contract->id, 'month' => '2026-09', 'due_date' => '2026-09-05',
            'amount' => 100000, 'status' => 'pending',
        ]);

        $this->actingAs($owner)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 40000, 'method' => 'cash']);
        $this->actingAs($owner)->getJson("/api/rents/{$contract->id}/contract")
            ->assertJsonPath('data.0.paid_amount', 40000)
            ->assertJsonPath('data.0.remaining', 60000)
            ->assertJsonPath('data.0.receipt_document_id', null);

        $this->actingAs($owner)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 60000, 'method' => 'cash']);
        (new GenerateRentReceiptPdf($rent->id))->handle(); // queue sync en test : déjà exécuté, rejoué ici
        $receiptId = $rent->receipt()->value('id');

        $this->assertNotNull($receiptId);
        $this->assertSame(1, $rent->receipt()->count()); // idempotent
        $this->actingAs($owner)->getJson('/api/rents')
            ->assertJsonPath('data.0.remaining', 0)
            ->assertJsonPath('data.0.receipt_document_id', $receiptId);
    }

    public function test_overdue_filter_returns_only_unpaid_past_due_rents(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [$contract] = $this->lease($owner);
        foreach ([['2026-01', 'pending', '2026-01-05'], ['2026-02', 'paid', '2026-02-05'], ['2099-01', 'pending', '2099-01-05']] as [$m, $st, $due]) {
            Rent::create(['contract_id' => $contract->id, 'month' => $m, 'due_date' => $due, 'amount' => 1, 'status' => $st]);
        }

        $this->actingAs($owner)->getJson('/api/rents?overdue=1')
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.month', '2026-01');
        $this->actingAs($owner)->getJson('/api/rents?status=paid')->assertJsonPath('total', 1);
    }
}
