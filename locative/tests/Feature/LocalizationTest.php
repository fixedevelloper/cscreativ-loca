<?php

namespace Tests\Feature;

use App\Jobs\GenerateRentReceiptPdf;
use App\Models\Rent;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Notifications\RentOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

/** Phase 3 : API, emails et PDF en français (défaut) et en anglais. */
class LocalizationTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    public function test_accept_language_selects_api_and_validation_language(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->postJson('/api/users', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.phone.0', 'The phone number field is required.');

        $this->withHeader('Accept-Language', 'fr-FR')->postJson('/api/users', [])
            ->assertJsonPath('message', 'Données invalides.')
            ->assertJsonPath('errors.phone.0', 'Le champ téléphone est obligatoire.');

        // Sans en-tête : français par défaut.
        $this->postJson('/api/login', ['email' => 'x@example.test', 'password' => 'mauvais'])
            ->assertJsonPath('message', 'Identifiants invalides.');
    }

    public function test_user_preference_applies_when_no_header_is_sent(): void
    {
        $owner = $this->user('owner');
        $owner->update(['locale' => 'en']);

        $this->actingAs($owner)->withHeader('Accept-Language', '')->getJson('/api/properties')
            ->assertForbidden()
            ->assertJsonPath('message', 'No active subscription found. Please subscribe.');
    }

    public function test_framework_http_errors_are_translated(): void
    {
        $tenant = $this->user('tenant');

        $this->actingAs($tenant)->withHeader('Accept-Language', 'en')
            ->postJson('/api/properties', ['name' => 'x'])
            ->assertForbidden()->assertJsonPath('message', 'Access denied.');
    }

    public function test_profile_stores_the_chosen_language(): void
    {
        $user = $this->user('tenant');

        $this->actingAs($user)->putJson('/api/me', ['locale' => 'en'])
            ->assertOk()->assertJsonPath('message', 'Profile updated.')->assertJsonPath('data.locale', 'en');
        $this->actingAs($user)->putJson('/api/me', ['locale' => 'de'])->assertStatus(422);
    }

    public function test_tenant_invitation_is_sent_in_the_request_language(): void
    {
        Notification::fake();
        $owner = $this->user('owner');
        $this->subscribe($owner);

        $this->actingAs($owner)->withHeader('Accept-Language', 'en')->postJson('/api/tenants', [
            'property_id' => $this->property($owner)->id, 'name' => 'Paul', 'email' => 'paul@example.test',
            'start_date' => now()->toDateString(), 'rent_amount' => 1000,
        ])->assertCreated()->assertJsonPath('message', 'Tenant added: an invitation has been sent by email.');

        $tenant = User::where('email', 'paul@example.test')->firstOrFail();
        $this->assertSame('en', $tenant->locale);
        Notification::assertSentTo($tenant, AccountInvitation::class, function (AccountInvitation $n, $channels, $notifiable, $locale) {
            $this->assertSame('en', $locale);
            $mail = $n->toMail($notifiable);
            $this->assertStringContainsString('invitation=1', $mail->actionUrl);

            return true;
        });
    }

    public function test_invitation_and_reset_tokens_are_not_interchangeable(): void
    {
        $user = $this->user('tenant');
        $invitationToken = Password::broker('invitations')->createToken($user);

        $payload = ['token' => $invitationToken, 'email' => $user->email, 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp'];
        $this->postJson('/api/reset-password', $payload)->assertStatus(422); // pas un lien de réinitialisation
        $this->postJson('/api/reset-password', $payload + ['invitation' => true])->assertOk();

        $resetToken = Password::broker('users')->createToken($user->fresh());
        $this->postJson('/api/reset-password', ['token' => $resetToken, 'email' => $user->email, 'invitation' => true,
            'password' => 'autre-mdp', 'password_confirmation' => 'autre-mdp'])->assertStatus(422);
    }

    public function test_invitation_link_stays_valid_for_72_hours_unlike_reset_links(): void
    {
        $user = $this->user('tenant');
        $token = Password::broker('invitations')->createToken($user);
        $this->travel(48)->hours();

        $this->postJson('/api/reset-password', ['token' => $token, 'email' => $user->email, 'invitation' => true,
            'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp'])->assertOk();
    }

    public function test_receipt_and_reminder_use_the_tenants_language(): void
    {
        Storage::fake('local');
        [$contract, $tenantUser] = $this->lease($this->user('owner'), 'Villa');
        $tenantUser->update(['locale' => 'en']);
        $rent = Rent::create(['contract_id' => $contract->id, 'month' => '2026-09', 'due_date' => '2026-09-05',
            'amount' => 1000, 'status' => 'paid']);

        app()->setLocale('fr'); // l'action est déclenchée par un bailleur francophone
        (new GenerateRentReceiptPdf($rent->id))->handle();
        $this->assertSame('Receipt 2026-09', $rent->receipt()->value('title'));

        $notification = new RentOverdueNotification($rent, 'j15');
        $subject = (fn () => $this->withLocale('en', fn () => $notification->toMail($tenantUser)->subject))
            ->call(new class { use \Illuminate\Support\Traits\Localizable; });
        $this->assertSame('Formal notice: unpaid rent (Villa)', $subject);
        $this->assertSame('en', $tenantUser->preferredLocale());
    }
}
