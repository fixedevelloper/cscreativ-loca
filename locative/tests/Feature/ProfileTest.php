<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRentalData;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use BuildsRentalData, RefreshDatabase;

    public function test_every_role_can_view_and_update_its_profile_without_subscription(): void
    {
        foreach (['owner', 'agent', 'tenant', 'admin'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('data.email', $user->email);
            $this->actingAs($user)->putJson('/api/me', ['name' => 'Nouveau nom', 'phone' => '699000000', 'role' => 'admin'])
                ->assertOk()->assertJsonPath('data.name', 'Nouveau nom');
            $this->assertSame($role, $user->fresh()->role); // rôle non modifiable
        }
    }

    public function test_password_change_requires_current_password_and_logs_out_other_devices(): void
    {
        $user = $this->user('tenant');
        $current = $user->createToken('current')->plainTextToken;
        $user->createToken('other');

        $this->withToken($current)->putJson('/api/me/password', [
            'current_password' => 'faux', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->app['auth']->forgetGuards();
        $this->withToken($current)->putJson('/api/me/password', [
            'current_password' => 'password123', 'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp',
        ])->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nouveau-mdp'])->assertOk();
    }
}
