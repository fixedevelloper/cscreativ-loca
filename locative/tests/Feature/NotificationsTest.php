<?php

namespace Tests\Feature;

use App\Jobs\GenerateRentReceiptPdf;
use App\Jobs\SendRentReminder;
use App\Models\Rent;
use App\Service\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/** Phase 4 : notifications dans l'application (cloche) et SMS. */
class NotificationsTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    /** SMS capturés par le pilote de test. */
    private array $sms = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->app->instance(SmsSender::class, new class($this->sms) implements SmsSender {
            public function __construct(private array &$box) {}
            public function send(string $to, string $message): void { $this->box[] = compact('to', 'message'); }
        });
    }

    private function scenario(): array
    {
        $owner = $this->user('owner');
        $this->subscribe($owner);
        [$contract, $tenantUser] = $this->lease($owner, 'Villa');
        $rent = Rent::create(['contract_id' => $contract->id, 'month' => '2026-09', 'due_date' => '2026-09-05',
            'amount' => 100000, 'status' => 'pending']);

        return [$owner, $tenantUser, $rent];
    }

    public function test_owner_is_notified_of_payments_recorded_by_someone_else_only(): void
    {
        [$owner, $tenantUser, $rent] = $this->scenario();
        $agent = $this->user('agent');
        $agent->owners()->attach($owner->id);

        $this->actingAs($owner)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 10000, 'method' => 'cash']);
        $this->assertSame(0, $owner->notifications()->count());

        $this->actingAs($agent)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 90000, 'method' => 'bank']);
        $notification = $owner->notifications()->first();
        $this->assertSame('rent_paid', $notification->data['type']);
        $this->assertSame('Villa', $notification->data['params']['property']);
        $this->assertEquals(90000, $notification->data['params']['amount']);
    }

    public function test_tenant_is_notified_once_when_the_receipt_is_ready(): void
    {
        [$owner, $tenantUser, $rent] = $this->scenario();

        $this->actingAs($owner)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 100000, 'method' => 'cash']);
        (new GenerateRentReceiptPdf($rent->id))->handle(); // rejeu du job

        $this->assertSame(1, $tenantUser->notifications()->count());
        $this->assertSame('receipt_available', $tenantUser->notifications()->first()->data['type']);
    }

    public function test_bell_endpoints_list_count_and_mark_as_read(): void
    {
        [$owner, $tenantUser, $rent] = $this->scenario();
        $agent = $this->user('agent');
        $agent->owners()->attach($owner->id);
        $this->actingAs($agent)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 10000, 'method' => 'cash']);
        $this->actingAs($agent)->postJson('/api/payments', ['rent_id' => $rent->id, 'amount' => 10000, 'method' => 'cash']);

        $list = $this->actingAs($owner)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('unread', 2)
            ->assertJsonPath('data.0.type', 'rent_partially_paid')
            ->assertJsonPath('data.0.link', '/rents')
            ->json('data');

        $this->actingAs($owner)->patchJson("/api/notifications/{$list[0]['id']}/read")->assertJsonPath('data.unread', 1);
        $this->actingAs($tenantUser)->patchJson("/api/notifications/{$list[1]['id']}/read")->assertNotFound();
        $this->actingAs($owner)->postJson('/api/notifications/read-all')->assertJsonPath('data.unread', 0);
        $this->actingAs($owner)->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread', 0);
    }

    public function test_overdue_reminder_is_stored_in_app_and_sent_by_sms_at_serious_stages(): void
    {
        [, $tenantUser, $rent] = $this->scenario();
        $tenantUser->update(['phone' => '677000000']);

        (new SendRentReminder($rent->id, 'j3'))->handle();
        $this->assertSame([], $this->sms, 'pas de SMS au premier rappel');

        (new SendRentReminder($rent->id, 'j15'))->handle();
        $this->assertEqualsCanonicalizing(['rent_overdue_j3', 'rent_overdue_j15'],
            $tenantUser->notifications()->get()->pluck('data.type')->all());
        $this->assertCount(1, $this->sms);
        $this->assertSame('237677000000', $this->sms[0]['to']);
        $this->assertStringContainsString('Villa', $this->sms[0]['message']);
    }
}
