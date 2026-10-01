<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/**
 * Non-régression des correctifs P1 de l'audit du 2026-10-01 (AUDIT.txt).
 */
class P1FixesTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ---- B6 : webhook MoMo ----------------------------------------------------

    private function pendingPayment(): SubscriptionPayment
    {
        $owner = $this->user('owner');
        $subscription = $this->subscribe($owner, 'pro', 'pending');

        return SubscriptionPayment::create([
            'user_id' => $owner->id,
            'subscription_id' => $subscription->id,
            'plan_name' => 'pro',
            'amount' => 15000,
            'status' => 'pending',
            'reference' => 'ref-123',
        ]);
    }

    public function test_momo_webhook_reaches_controller_and_activates_after_reverification(): void
    {
        Http::fake([
            '*/token/' => Http::response(['access_token' => 't']),
            '*/requesttopay/ref-123' => Http::response(['status' => 'SUCCESSFUL']),
        ]);
        $payment = $this->pendingPayment();

        // Pas de token CSRF ni Sanctum : c'est ce qu'envoie MoMo.
        $this->putJson('/api/momo/callback', ['referenceId' => 'ref-123'])->assertOk();

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('active', $payment->subscription->fresh()->status);
    }

    public function test_momo_webhook_body_alone_never_activates(): void
    {
        Http::fake([
            '*/token/' => Http::response(['access_token' => 't']),
            '*/requesttopay/ref-123' => Http::response(['status' => 'PENDING']),
        ]);
        $payment = $this->pendingPayment();

        $this->postJson('/api/momo/callback', ['referenceId' => 'ref-123', 'status' => 'SUCCESSFUL'])->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ---- B7 : recherches ------------------------------------------------------

    public function test_search_filters_work_instead_of_failing(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [$contract, $tenantUser] = $this->lease($owner, 'Villa Bonapriso');
        $this->rentWithPayment($contract);
        $this->lease($owner, 'Studio Akwa');

        $this->actingAs($owner)->getJson('/api/tenants?search=' . urlencode($tenantUser->name))
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($owner)->getJson('/api/contracts?search=Bonapriso')
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($owner)->getJson('/api/payments?search=Bonapriso')
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($owner)->getJson('/api/documents?search=x')
            ->assertOk()->assertJsonPath('total', 0);
    }

    // ---- B8 : routes et suppression de locataire ----------------------------

    public function test_unimplemented_actions_are_not_routed(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [$contract] = $this->lease($owner);
        $payment = $this->rentWithPayment($contract);

        $this->actingAs($owner)->deleteJson("/api/payments/{$payment->id}")->assertNotFound();
        $this->actingAs($owner)->putJson("/api/properties/{$contract->property_id}", [])->assertNotFound();
        $this->actingAs($owner)->getJson("/api/contracts/{$contract->id}")->assertNotFound();
        $this->assertNotNull($payment->fresh());
    }

    public function test_owner_removes_tenant_and_lease_is_terminated_not_erased(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [$contract] = $this->lease($owner);
        $tenant = $contract->tenant;
        $this->assertSame('occupied', $contract->property->fresh()->status);

        $this->actingAs($this->user('owner'))->deleteJson("/api/tenants/{$tenant->id}")->assertForbidden();

        $this->actingAs($owner)->deleteJson("/api/tenants/{$tenant->id}")->assertOk();

        $this->assertSoftDeleted($tenant);
        $this->assertSame('terminated', $contract->fresh()->status);
        $this->assertSame('free', $contract->property->fresh()->status);
    }

    // ---- S6 : tokens API --------------------------------------------------------

    public function test_api_tokens_expire_and_logout_revokes_only_current_token(): void
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        $first = $owner->createToken('a')->plainTextToken;
        $second = $owner->createToken('b')->plainTextToken;

        $this->withToken($first)->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($first)->getJson('/api/subscriptions')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/subscriptions')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->travel(config('sanctum.expiration') + 1)->minutes();
        $this->withToken($second)->getJson('/api/subscriptions')->assertUnauthorized();
    }

    // ---- 403 abonnement : code dédié pour la redirection frontend -----------

    public function test_missing_subscription_returns_dedicated_code(): void
    {
        $this->actingAs($this->user('owner'))->getJson('/api/properties')
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_required');

        $this->assertSame(0, Subscription::count());
    }
}
