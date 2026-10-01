<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/**
 * Non-régression des correctifs P0 de l'audit du 2026-10-01 (AUDIT.txt).
 */
class P0SecurityTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ---- S1 / S3 : accès aux documents ------------------------------------

    public function test_tenant_cannot_download_another_tenants_shared_document(): void
    {
        $ownerA = $this->user('owner');
        $ownerB = $this->user('owner');
        [$contractA, $tenantA] = $this->lease($ownerA);
        [$contractB] = $this->lease($ownerB);

        $own = $this->document($contractA, $ownerA);
        $other = $this->document($contractB, $ownerB);

        $this->actingAs($tenantA)->get("/api/documents/{$own->id}/download")->assertOk();
        $this->actingAs($tenantA)->getJson("/api/documents/{$other->id}/download")->assertForbidden();

        $ids = collect($this->actingAs($tenantA)->getJson('/api/documents')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$own->id], $ids->all());
    }

    public function test_agent_only_sees_documents_of_managed_owners(): void
    {
        $managed = $this->user('owner');
        $other = $this->user('owner');
        $this->subscribe($managed);
        $agent = $this->user('agent');
        $agent->owners()->attach($managed->id);

        [$contractManaged] = $this->lease($managed);
        [$contractOther] = $this->lease($other);
        $visible = $this->document($contractManaged, $managed);
        $hidden = $this->document($contractOther, $other);

        $ids = collect($this->actingAs($agent)->getJson('/api/documents')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$visible->id], $ids->all());
        $this->actingAs($agent)->getJson("/api/documents/{$hidden->id}/download")->assertForbidden();
    }

    // ---- S2 : rattachement à un contrat d'autrui -------------------------

    public function test_owner_cannot_attach_document_to_another_owners_contract(): void
    {
        Queue::fake();
        $attacker = $this->user('owner');
        $this->subscribe($attacker);
        [$victimContract] = $this->lease($this->user('owner'));

        $this->actingAs($attacker)->postJson('/api/documents', [
            'contract_id' => $victimContract->id,
            'title' => 'x',
            'type' => 'contract',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('documents', 0);
    }

    // ---- B1 / B2 / B3 : abonnements ----------------------------------------

    public function test_plans_endpoint_exposes_server_catalogue(): void
    {
        $this->getJson('/api/plans')->assertOk()
            ->assertJsonPath('data.3.key', 'agency')
            ->assertJsonPath('data.3.property_limit', null);
    }

    public function test_agency_subscription_and_cancel_work(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)->postJson('/api/subscriptions', ['plan_name' => 'agency'])
            ->assertCreated()
            ->assertJsonPath('data.requires_payment', true);

        $subscription = Subscription::where('user_id', $owner->id)->firstOrFail();
        $this->assertNull($subscription->property_limit);

        $this->actingAs($owner)->patchJson("/api/subscriptions/{$subscription->id}/cancel")->assertOk();
        $this->assertSame('cancelled', $subscription->fresh()->status);
    }

    public function test_pending_paid_plan_does_not_cut_current_subscription(): void
    {
        $owner = $this->user('owner');
        $current = $this->subscribe($owner, 'starter');

        $this->actingAs($owner)->postJson('/api/subscriptions', ['plan_name' => 'pro'])->assertCreated();

        $this->assertSame('active', $current->fresh()->status);
        $this->actingAs($owner)->getJson('/api/rents')->assertOk();
    }

    public function test_free_plan_is_activated_immediately(): void
    {
        $owner = $this->user('owner');

        $this->actingAs($owner)->postJson('/api/subscriptions', ['plan_name' => 'free'])
            ->assertCreated()
            ->assertJsonPath('data.requires_payment', false);

        $this->assertNotNull($owner->activeSubscription());
    }

    // ---- B4 : création de locataire + invitation ----------------------------

    public function test_owner_can_create_tenant_and_tenant_receives_frontend_link(): void
    {
        Notification::fake();
        $owner = $this->user('owner');
        $this->subscribe($owner);
        $property = $this->property($owner);

        $this->actingAs($owner)->postJson('/api/tenants', [
            'property_id' => $property->id,
            'name' => 'Loc',
            'email' => 'loc@example.test',
            'start_date' => now()->toDateString(),
            'rent_amount' => 50000,
        ])->assertCreated();

        $tenantUser = User::where('email', 'loc@example.test')->firstOrFail();
        Notification::assertSentTo($tenantUser, AccountInvitation::class, function (AccountInvitation $n) use ($tenantUser) {
            $url = $n->toMail($tenantUser)->actionUrl;

            return str_starts_with($url, config('app.frontend_url') . '/auth/reset-password?token=');
        });
    }

    public function test_tenant_creation_marks_property_occupied_and_blocks_a_second_tenant(): void
    {
        Notification::fake();
        $owner = $this->user('owner');
        $this->subscribe($owner);
        $property = $this->property($owner);
        $payload = fn (string $email) => [
            'property_id' => $property->id,
            'name' => 'Loc',
            'email' => $email,
            'start_date' => now()->toDateString(),
            'rent_amount' => 50000,
        ];

        $this->actingAs($owner)->postJson('/api/tenants', $payload('a@example.test'))->assertCreated();
        $this->assertSame('occupied', $property->fresh()->status);
        $this->actingAs($owner)->getJson('/api/properties')->assertJsonPath('data.0.status', 'occupied');

        // 409 même si la limite de locataires du plan est aussi atteinte.
        $owner->subscriptions()->update(['tenant_limit' => 1]);
        $this->actingAs($owner)->postJson('/api/tenants', $payload('b@example.test'))
            ->assertStatus(409)->assertJsonPath('message', 'Ce bien est déjà occupé.');
        $this->assertDatabaseMissing('users', ['email' => 'b@example.test']);
    }

    public function test_reset_password_sets_a_usable_password(): void
    {
        $user = $this->user('tenant');
        $token = app('auth.password.broker')->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nouveau-mdp',
            'password_confirmation' => 'nouveau-mdp',
        ])->assertOk();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nouveau-mdp'])->assertOk();
    }

    // ---- B5 : accès locataire / agent ---------------------------------------

    public function test_tenant_and_agent_are_not_blocked_by_subscription_middleware(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [, $tenantUser] = $this->lease($owner);
        $agent = $this->user('agent');
        $agent->owners()->attach($owner->id);
        $orphanAgent = $this->user('agent');

        $this->actingAs($tenantUser)->getJson('/api/rents')->assertOk();
        $this->actingAs($agent)->getJson('/api/properties')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($orphanAgent)->getJson('/api/properties')->assertForbidden();
    }
}
