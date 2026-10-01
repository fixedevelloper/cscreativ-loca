<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/** Phase 4 : demandes d'intervention (tickets). */
class MaintenanceTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    private $owner;
    private $agent;
    private $tenantUser;
    private $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->user('owner');
        $this->subscribe($this->owner);
        [$this->contract, $this->tenantUser] = $this->lease($this->owner, 'Villa');
        $this->agent = $this->user('agent');
        $this->agent->owners()->attach($this->owner->id);
    }

    private function ticket(array $overrides = []): int
    {
        return $this->actingAs($this->tenantUser)->postJson('/api/maintenance-requests', $overrides + [
            'contract_id' => $this->contract->id,
            'title' => 'Fuite sous l\'évier',
            'description' => 'Le siphon goutte en continu.',
            'category' => 'plumbing',
            'priority' => 'urgent',
        ])->assertCreated()->json('data.id');
    }

    public function test_tenant_reports_an_issue_and_management_is_notified(): void
    {
        $id = $this->ticket();

        $this->assertSame('Villa', MaintenanceRequest::find($id)->property->name);
        $this->assertSame('maintenance_created', $this->owner->notifications()->first()->data['type']);
        $this->assertSame(1, $this->agent->notifications()->count());
        $this->assertSame(0, $this->tenantUser->notifications()->count());
    }

    public function test_tenant_is_restricted_to_own_active_lease(): void
    {
        [$otherContract] = $this->lease($this->user('owner'));

        $this->actingAs($this->tenantUser)->postJson('/api/maintenance-requests', [
            'contract_id' => $otherContract->id, 'title' => 'x', 'description' => 'x', 'category' => 'other', 'priority' => 'low',
        ])->assertForbidden();

        $this->actingAs($this->tenantUser)->postJson('/api/maintenance-requests', [
            'contract_id' => $this->contract->id, 'property_id' => $otherContract->property_id,
            'title' => 'x', 'description' => 'x', 'category' => 'other', 'priority' => 'low',
        ])->assertStatus(422)->assertJsonValidationErrors('property_id');
    }

    public function test_owner_creates_on_own_property_only(): void
    {
        $this->actingAs($this->owner)->postJson('/api/maintenance-requests', [
            'property_id' => $this->contract->property_id, 'title' => 'Peinture', 'description' => 'Façade',
            'category' => 'structure', 'priority' => 'low',
        ])->assertCreated();

        [$otherContract] = $this->lease($this->user('owner'));
        $this->actingAs($this->owner)->postJson('/api/maintenance-requests', [
            'property_id' => $otherContract->property_id, 'title' => 'x', 'description' => 'x',
            'category' => 'other', 'priority' => 'low',
        ])->assertForbidden();
    }

    public function test_visibility_is_scoped(): void
    {
        $id = $this->ticket();
        $outsider = $this->user('owner');
        $this->subscribe($outsider);

        $this->actingAs($outsider)->getJson('/api/maintenance-requests')->assertJsonPath('total', 0);
        $this->actingAs($outsider)->getJson("/api/maintenance-requests/{$id}")->assertForbidden();
        $this->actingAs($this->agent)->getJson("/api/maintenance-requests/{$id}")->assertOk()
            ->assertJsonPath('data.tenant_name', $this->tenantUser->name);
    }

    public function test_status_workflow_and_notifications(): void
    {
        $id = $this->ticket();

        $this->actingAs($this->tenantUser)->patchJson("/api/maintenance-requests/{$id}/status", ['status' => 'in_progress'])
            ->assertForbidden();

        $this->actingAs($this->agent)->patchJson("/api/maintenance-requests/{$id}/status", ['status' => 'in_progress'])
            ->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->assertSame('maintenance_status', $this->tenantUser->notifications()->first()->data['type']);

        $this->actingAs($this->tenantUser)->patchJson("/api/maintenance-requests/{$id}/status", ['status' => 'closed'])
            ->assertForbidden(); // pas encore résolue

        $this->actingAs($this->owner)->patchJson("/api/maintenance-requests/{$id}/status", ['status' => 'resolved'])->assertOk();
        $this->actingAs($this->tenantUser)->patchJson("/api/maintenance-requests/{$id}/status", ['status' => 'closed'])
            ->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertNotNull(MaintenanceRequest::find($id)->resolved_at);
    }

    public function test_comments_thread(): void
    {
        $id = $this->ticket();

        $this->actingAs($this->owner)->postJson("/api/maintenance-requests/{$id}/comments", ['body' => 'Plombier jeudi 10h.'])
            ->assertCreated()->assertJsonPath('data.comments.0.body', 'Plombier jeudi 10h.');
        $this->assertSame('maintenance_comment', $this->tenantUser->notifications()->first()->data['type']);

        $this->actingAs($this->user('tenant'))->postJson("/api/maintenance-requests/{$id}/comments", ['body' => 'x'])
            ->assertForbidden();
    }

    public function test_open_urgent_requests_come_first(): void
    {
        $this->ticket(['title' => 'Basse', 'priority' => 'low']);
        $this->ticket(['title' => 'Urgente', 'priority' => 'urgent']);

        $this->actingAs($this->owner)->getJson('/api/maintenance-requests')
            ->assertJsonPath('data.0.title', 'Urgente')
            ->assertJsonPath('data.0.comments_count', 0);
    }
}
