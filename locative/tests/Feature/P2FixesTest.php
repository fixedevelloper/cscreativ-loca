<?php

namespace Tests\Feature;

use App\Jobs\GenerateContractPdf;
use App\Jobs\GenerateRentForContract;
use App\Jobs\GenerateRentReceiptPdf;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Rent;
use App\Models\SubscriptionPayment;
use App\Service\MomoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/**
 * Non-régression des correctifs P2 de l'audit du 2026-10-01 (AUDIT.txt).
 */
class P2FixesTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ---- Limites de plan -----------------------------------------------------

    public function test_property_limit_is_enforced_and_null_means_unlimited(): void
    {
        $limited = $this->user('owner');
        $this->subscribe($limited)->update(['property_limit' => 1]);
        $unlimited = $this->user('owner');
        $this->subscribe($unlimited);

        $payload = [
            'name' => 'B', 'address' => 'a', 'city' => 'c', 'type' => 'studio',
            'surface' => '10', 'number_piece' => 1, 'monthly_rent' => 1000,
        ];

        $this->actingAs($limited)->postJson('/api/properties', $payload)->assertOk();
        $this->actingAs($limited)->postJson('/api/properties', $payload)
            ->assertForbidden()->assertJsonPath('message', 'Limite de biens atteinte pour votre plan.');

        $this->actingAs($unlimited)->postJson('/api/properties', $payload)->assertOk();
        $this->actingAs($unlimited)->postJson('/api/properties', $payload)->assertOk();
    }

    public function test_tenant_limit_is_enforced_on_tenant_creation_even_for_agents(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner)->update(['tenant_limit' => 1]);
        $this->lease($owner); // 1 locataire : limite atteinte
        $free = $this->property($owner, 'Libre');
        $agent = $this->user('agent');
        $agent->owners()->attach($owner->id);

        $this->actingAs($agent)->postJson('/api/tenants', [
            'property_id' => $free->id,
            'name' => 'Loc',
            'email' => 'loc2@example.test',
            'start_date' => now()->toDateString(),
            'rent_amount' => 1000,
        ])->assertForbidden()->assertJsonPath('message', 'Limite de locataires atteinte pour le plan du bailleur.');
    }

    // ---- Encaissements --------------------------------------------------------

    private function pendingRent(): array
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [$contract] = $this->lease($owner);
        $rent = Rent::create([
            'contract_id' => $contract->id, 'month' => '2026-10', 'due_date' => '2026-10-05',
            'amount' => 100000, 'status' => 'pending',
        ]);

        return [$owner, $rent];
    }

    public function test_overpayment_is_rejected_and_receipt_generated_once(): void
    {
        Queue::fake();
        [$owner, $rent] = $this->pendingRent();

        $this->actingAs($owner)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 150000, 'method' => 'cash'])
            ->assertStatus(422);

        $this->actingAs($owner)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 40000, 'method' => 'cash'])
            ->assertCreated();
        Queue::assertNotPushed(GenerateRentReceiptPdf::class);

        $this->actingAs($owner)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 60000, 'method' => 'bank'])
            ->assertCreated();
        $this->assertSame('paid', $rent->fresh()->status);

        $this->actingAs($owner)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 1, 'method' => 'cash'])
            ->assertStatus(422);

        Queue::assertPushed(GenerateRentReceiptPdf::class, 1);
    }

    // ---- Génération des loyers / fin de bail --------------------------------

    public function test_no_rent_is_generated_after_lease_end(): void
    {
        $this->travelTo('2026-11-01 02:00');
        [$contract] = $this->lease($this->user('owner'));
        $contract->update(['start_date' => '2026-01-01', 'end_date' => '2026-10-31']);

        (new GenerateRentForContract($contract->id))->handle();

        $this->assertSame(0, $contract->rents()->count());
    }

    public function test_annual_lease_is_billed_on_anniversary_month_only(): void
    {
        [$contract] = $this->lease($this->user('owner'));
        $contract->forceFill(['start_date' => '2025-03-01', 'periodic' => 'annual'])->save();

        $this->travelTo('2026-02-01 02:00');
        (new GenerateRentForContract($contract->id))->handle();
        $this->assertSame(0, $contract->rents()->count());

        $this->travelTo('2026-03-01 02:00');
        (new GenerateRentForContract($contract->id))->handle();
        $this->assertSame(1, $contract->rents()->count());
    }

    public function test_expired_leases_are_closed_and_property_freed(): void
    {
        [$contract] = $this->lease($this->user('owner'));
        $contract->update(['end_date' => now()->subDay()->toDateString()]);
        $this->assertSame('occupied', $contract->property->fresh()->status);

        $this->artisan('app:expire-contracts')->assertSuccessful();

        $this->assertSame('expired', $contract->fresh()->status);
        $this->assertSame('free', $contract->property->fresh()->status);
    }

    // ---- Documents / états des lieux ---------------------------------------

    public function test_generated_contract_pdf_replaces_upload_without_orphan_or_overwrite(): void
    {
        $owner = $this->user('owner');
        [$contract] = $this->lease($owner);
        $first = $this->document($contract, $owner);
        Storage::disk('local')->put('documents/second.pdf', 'pdf');
        $second = Document::create([
            'contract_id' => $contract->id, 'uploaded_by' => $owner->id, 'uploader_role' => 'owner',
            'title' => 'Bail', 'type' => 'contract', 'file_path' => 'documents/second.pdf',
        ]);

        (new GenerateContractPdf($first->id, $contract->id))->handle();
        (new GenerateContractPdf($second->id, $contract->id))->handle();

        $this->assertNotSame($first->fresh()->file_path, $second->fresh()->file_path);
        Storage::disk('local')->assertExists([$first->fresh()->file_path, $second->fresh()->file_path]);
        Storage::disk('local')->assertMissing('documents/second.pdf');
    }

    public function test_inventory_cannot_reference_a_contract_of_another_property(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [$contractA] = $this->lease($owner, 'A');
        [$contractB] = $this->lease($owner, 'B');

        $this->actingAs($owner)->postJson('/api/inventories', [
            'propertyId' => $contractA->property_id,
            'contract_id' => $contractB->id,
            'type' => 'entry',
            'items' => [['item_name' => 'Porte', 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('contract_id');

        // contract_id facultatif : plus d'erreur "undefined array key".
        $this->actingAs($owner)->postJson('/api/inventories', [
            'propertyId' => $contractA->property_id,
            'type' => 'entry',
            'items' => [['item_name' => 'Porte', 'quantity' => 1]],
        ])->assertCreated();
    }

    // ---- MoMo -------------------------------------------------------------------

    public function test_momo_token_is_cached_and_failures_raise(): void
    {
        Http::fake([
            '*/token/' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*/requesttopay/ok' => Http::response(['status' => 'PENDING']),
            '*/v1_0/requesttopay' => Http::response([], 500),
        ]);
        $momo = app(MomoService::class);

        $momo->getPaymentStatus('ok');
        $momo->getPaymentStatus('ok');
        Http::assertSentCount(3); // 1 token + 2 statuts

        $this->expectException(RuntimeException::class);
        $momo->requestToPay('ref', '677000000', 5000);
    }

    public function test_request_to_pay_prefixes_country_code(): void
    {
        Http::fake([
            '*/token/' => Http::response(['access_token' => 't']),
            '*/v1_0/requesttopay' => Http::response([], 202),
        ]);

        app(MomoService::class)->requestToPay('ref', '677000000', 5000);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1_0/requesttopay')
            && $request['payer']['partyId'] === '237677000000');
    }

    public function test_abandoned_pending_payments_are_closed_not_polled_forever(): void
    {
        Http::fake(['*' => Http::response(['access_token' => 't', 'status' => 'PENDING'])]);
        $owner = $this->user('owner');
        $subscription = $this->subscribe($owner, 'pro', 'pending');
        $old = SubscriptionPayment::create([
            'user_id' => $owner->id, 'subscription_id' => $subscription->id, 'plan_name' => 'pro',
            'amount' => 15000, 'status' => 'pending', 'reference' => 'old',
        ]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('app:get-status-momo')->assertSuccessful();

        $this->assertSame('failed', $old->fresh()->status);
    }

    // ---- Limite de débit derrière le proxy Next -----------------------------

    public function test_login_throttle_is_per_client_ip_behind_trusted_proxy(): void
    {
        $owner = $this->user('owner');
        $attempt = fn (string $ip) => $this->withHeader('X-Forwarded-For', $ip)
            ->postJson('/api/login', ['email' => $owner->email, 'password' => 'mauvais']);

        foreach (range(1, 5) as $i) {
            $attempt('203.0.113.1')->assertUnauthorized();
        }
        $attempt('203.0.113.1')->assertStatus(429);
        $attempt('203.0.113.2')->assertUnauthorized();
    }

    public function test_contract_model_still_defaults_to_monthly(): void
    {
        [$contract] = $this->lease($this->user('owner'));

        $this->assertSame('monthly', Contract::find($contract->id)->periodic);
    }
}
