<?php

namespace Tests\Feature;

use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/**
 * Écrans d'administration (phase 2) : invitation du personnel, suspension,
 * rattachements agent-bailleurs, abonnements et revenus.
 */
class AdminTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    public function test_staff_is_invited_instead_of_receiving_an_admin_chosen_password(): void
    {
        Notification::fake();
        $admin = $this->user('admin');

        $this->actingAs($admin)->postJson('/api/users/staff', [
            'name' => 'Agent', 'email' => 'agent@example.test', 'phone' => '677', 'role' => 'agent',
            'password' => 'choisi-par-admin', // ignoré
        ])->assertCreated()->assertJsonPath('data.role', 'agent');

        $agent = User::where('email', 'agent@example.test')->firstOrFail();
        Notification::assertSentTo($agent, AccountInvitation::class);
        $this->postJson('/api/login', ['email' => 'agent@example.test', 'password' => 'choisi-par-admin'])
            ->assertUnauthorized();
    }

    public function test_suspension_blocks_login_and_revokes_tokens_until_reactivation(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('owner');
        $token = $owner->createToken('t')->plainTextToken;

        $this->actingAs($admin)->patchJson("/api/users/{$owner->id}/suspend")->assertOk();
        $this->assertSame(0, $owner->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/subscriptions')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $owner->email, 'password' => 'password123'])
            ->assertForbidden()->assertJsonPath('message', 'Compte suspendu. Contactez l\'administrateur de la plateforme.');

        $this->actingAs($admin)->patchJson("/api/users/{$owner->id}/reactivate")->assertOk();
        $this->postJson('/api/login', ['email' => $owner->email, 'password' => 'password123'])->assertOk();
    }

    public function test_admin_cannot_suspend_themselves_and_others_cannot_suspend(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('owner');

        $this->actingAs($admin)->patchJson("/api/users/{$admin->id}/suspend")->assertStatus(422);
        $this->actingAs($owner)->patchJson("/api/users/{$admin->id}/suspend")->assertForbidden();
    }

    public function test_admin_manages_agent_portfolio(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        [$a, $b] = [$this->user('owner'), $this->user('owner')];

        $this->actingAs($admin)->postJson("/api/agents/{$agent->id}/assign-owners", ['owner_ids' => [$a->id, $b->id]])->assertOk();
        $this->actingAs($admin)->getJson("/api/agents/{$agent->id}/owners")->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($admin)->deleteJson("/api/agents/{$agent->id}/owners/{$a->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($agent)->deleteJson("/api/agents/{$agent->id}/owners/{$b->id}")->assertForbidden();
    }

    public function test_subscription_overview_with_revenue(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('owner');
        $subscription = $this->subscribe($owner, 'pro');
        SubscriptionPayment::create([
            'user_id' => $owner->id, 'subscription_id' => $subscription->id, 'plan_name' => 'pro',
            'amount' => 15000, 'status' => 'success', 'reference' => 'r1',
        ]);
        $this->subscribe($this->user('owner'), 'starter', 'pending');

        $this->actingAs($admin)->getJson('/api/admin/subscriptions?status=active')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.user.id', $owner->id)
            ->assertJsonPath('summary.active_by_plan.pro', 1)
            ->assertJsonPath('summary.revenue_this_month', 15000)
            ->assertJsonPath('summary.revenue_total', 15000);

        $this->actingAs($owner)->getJson('/api/admin/subscriptions')->assertForbidden();
    }
}
