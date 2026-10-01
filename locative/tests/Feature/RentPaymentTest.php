<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Rent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/** Phase 4 : paiement du loyer en ligne par le locataire (MoMo simulé). */
class RentPaymentTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    private Rent $rent;
    private $tenantUser;
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = $this->user('owner');
        $this->subscribe($this->owner);
        [$contract, $this->tenantUser] = $this->lease($this->owner);
        $this->rent = Rent::create(['contract_id' => $contract->id, 'month' => '2026-10', 'due_date' => '2026-10-05',
            'amount' => 100000, 'status' => 'pending']);
    }

    /** Statut renvoyé par le MoMo simulé (modifiable en cours de test). */
    private string $momoStatus = 'PENDING';
    private bool $momoFaked = false;

    private function fakeMomo(string $status = 'PENDING'): void
    {
        $this->momoStatus = $status;
        if ($this->momoFaked) {
            return; // Http::fake() empile les réponses : on ne simule qu'une fois.
        }
        $this->momoFaked = true;

        Http::fake([
            '*/token/' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*/v1_0/requesttopay' => Http::response([], 202),
            '*/v1_0/requesttopay/*' => fn () => Http::response(['status' => $this->momoStatus]),
        ]);
    }

    public function test_tenant_pays_remaining_amount_and_rent_is_settled_on_confirmation(): void
    {
        $this->fakeMomo();
        $this->actingAs($this->owner)->postJson('/api/payments', ['rent_id' => $this->rent->id, 'amount' => 30000, 'method' => 'cash']);

        $reference = $this->actingAs($this->tenantUser)
            ->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])
            ->assertStatus(202)->assertJsonPath('data.amount', 70000)->json('data.reference');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1_0/requesttopay')
            && $r['payer']['partyId'] === '237677000000' && $r['amount'] === '70000' && $r['payerMessage'] === 'Loyer 2026-10');
        $this->assertSame('pending', Payment::where('reference', $reference)->value('status'));

        $this->fakeMomo('SUCCESSFUL');
        $this->actingAs($this->tenantUser)->postJson("/api/rent-payments/{$reference}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.rent_status', 'paid')
            ->assertJsonPath('data.remaining', 0);

        $this->assertNotNull($this->rent->fresh()->receipt);
    }

    public function test_only_the_leaseholder_can_pay(): void
    {
        $this->fakeMomo();
        $otherOwner = $this->user('owner');
        [, $otherTenant] = $this->lease($otherOwner);

        $this->actingAs($otherTenant)->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])->assertForbidden();
        $this->actingAs($this->owner)->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])->assertForbidden();
    }

    public function test_no_double_debit_while_a_request_is_pending_and_failed_payment_allows_retry(): void
    {
        $this->fakeMomo();
        $reference = $this->actingAs($this->tenantUser)
            ->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])->json('data.reference');

        $this->actingAs($this->tenantUser)->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])
            ->assertStatus(409);

        $this->fakeMomo('FAILED');
        $this->actingAs($this->tenantUser)->postJson("/api/rent-payments/{$reference}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'failed');

        $this->fakeMomo();
        $this->actingAs($this->tenantUser)->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])
            ->assertStatus(202);
    }

    public function test_paid_rent_cannot_be_paid_again(): void
    {
        $this->rent->update(['status' => 'paid']);

        $this->actingAs($this->tenantUser)->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])
            ->assertStatus(422)->assertJsonPath('message', 'Ce loyer est déjà soldé.');
    }

    public function test_reference_of_someone_else_cannot_be_confirmed(): void
    {
        $this->fakeMomo();
        $reference = $this->actingAs($this->tenantUser)
            ->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])->json('data.reference');

        $this->actingAs($this->owner)->postJson("/api/rent-payments/{$reference}/confirm")->assertNotFound();
    }

    public function test_webhook_and_polling_settle_rent_payments(): void
    {
        $this->fakeMomo();
        $reference = $this->actingAs($this->tenantUser)
            ->postJson("/api/rents/{$this->rent->id}/pay", ['phone' => '677000000'])->json('data.reference');

        // Webhook avec un body mensonger : seule la re-vérification compte.
        $this->postJson('/api/momo/callback', ['referenceId' => $reference, 'status' => 'SUCCESSFUL'])->assertOk();
        $this->assertSame('pending', Payment::where('reference', $reference)->value('status'));

        $this->fakeMomo('SUCCESSFUL');
        $this->artisan('app:get-status-momo')->assertSuccessful();
        $this->assertSame('success', Payment::where('reference', $reference)->value('status'));
        $this->assertSame('paid', $this->rent->fresh()->status);

        // Idempotent : un webhook tardif ne crée rien de plus.
        $this->postJson('/api/momo/callback', ['referenceId' => $reference])->assertOk();
        $this->assertSame(1, $this->rent->payments()->where('status', 'success')->count());
    }
}
