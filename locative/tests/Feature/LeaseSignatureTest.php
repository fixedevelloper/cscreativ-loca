<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/** Phase 4 : bail PDF et signature électronique simple. */
class LeaseSignatureTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    private $owner;
    private $agent;
    private $tenantUser;
    private $contract;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = $this->user('owner');
        $this->subscribe($this->owner);
        [$this->contract, $this->tenantUser] = $this->lease($this->owner, 'Villa');
        $this->agent = $this->user('agent');
        $this->agent->owners()->attach($this->owner->id);
    }

    private function generate(): int
    {
        return $this->actingAs($this->owner)->postJson("/api/contracts/{$this->contract->id}/lease")
            ->assertCreated()->json('data.lease_document_id');
    }

    public function test_owner_generates_a_lease_shared_with_the_tenant(): void
    {
        $documentId = $this->generate();

        $this->assertSame('lease_ready', $this->tenantUser->notifications()->first()->data['type']);
        $this->actingAs($this->tenantUser)->get("/api/documents/{$documentId}/download")->assertOk();
        $this->actingAs($this->tenantUser)->postJson("/api/contracts/{$this->contract->id}/lease")->assertForbidden();
    }

    public function test_signature_requires_a_document_and_explicit_consent(): void
    {
        $this->actingAs($this->tenantUser)->postJson("/api/contracts/{$this->contract->id}/sign", ['accept' => true])
            ->assertStatus(409);

        $this->generate();
        $this->actingAs($this->tenantUser)->postJson("/api/contracts/{$this->contract->id}/sign", [])
            ->assertStatus(422)->assertJsonValidationErrors('accept');
    }

    public function test_full_signature_flow_produces_a_signed_lease(): void
    {
        $this->generate();
        $leasePath = $this->contract->fresh()->leaseDocument->file_path;

        $this->actingAs($this->tenantUser)->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson("/api/contracts/{$this->contract->id}/sign", ['accept' => true])
            ->assertOk()->assertJsonPath('data.signatures.0.party', 'tenant');

        $signature = $this->contract->signatures()->first();
        $this->assertSame(hash('sha256', Storage::disk('local')->get($leasePath)), $signature->document_hash);
        $this->assertSame('lease_signed_by', $this->owner->notifications()->first()->data['type']);

        // Document figé dès la première signature.
        $this->actingAs($this->owner)->postJson("/api/contracts/{$this->contract->id}/lease")->assertStatus(409);
        $this->actingAs($this->tenantUser)->postJson("/api/contracts/{$this->contract->id}/sign", ['accept' => true])
            ->assertStatus(409);

        // L'agent signe pour le bailleur (mandataire).
        $this->actingAs($this->agent)->postJson("/api/contracts/{$this->contract->id}/sign", ['accept' => true])
            ->assertOk();

        $contract = $this->contract->fresh();
        $this->assertNotNull($contract->signed_at);
        Storage::disk('local')->assertExists($contract->signedDocument->file_path);
        $this->assertContains('lease_fully_signed', $this->tenantUser->notifications()->get()->pluck('data.type'));

        $this->actingAs($this->owner)->getJson('/api/contracts')
            ->assertJsonPath('data.0.signed_document_id', $contract->signed_document_id)
            ->assertJsonCount(2, 'data.0.signatures');
    }

    public function test_outsiders_cannot_sign(): void
    {
        $this->generate();
        [, $otherTenant] = $this->lease($this->user('owner'));

        $this->actingAs($otherTenant)->postJson("/api/contracts/{$this->contract->id}/sign", ['accept' => true])
            ->assertForbidden();
        $this->actingAs($this->user('agent'))->postJson("/api/contracts/{$this->contract->id}/sign", ['accept' => true])
            ->assertForbidden();
    }
}
