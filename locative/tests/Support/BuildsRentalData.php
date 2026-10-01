<?php

namespace Tests\Support;

use App\Models\Contract;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rent;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Scénarios métier explicites pour les tests de fonctionnalités (bailleur,
 * bail, document…), aux valeurs fixes pour des assertions lisibles. Pour des
 * données quelconques, préférer les factories (database/factories).
 */
trait BuildsRentalData
{
    private int $userSequence = 0;

    protected function user(string $role): User
    {
        $this->userSequence++;

        return User::create([
            'name' => "{$role} {$this->userSequence}",
            'email' => "{$role}{$this->userSequence}@example.test",
            'password' => bcrypt('password123'),
            'role' => $role,
        ]);
    }

    protected function subscribe(User $user, string $plan = 'pro', string $status = 'active'): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id,
            'plan_name' => $plan,
            'price' => 0,
            'property_limit' => null,
            'tenant_limit' => null,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => $status,
        ]);
    }

    protected function property(User $owner, string $name = 'Bien'): Property
    {
        return Property::create([
            'user_id' => $owner->id,
            'name' => $name,
            'address' => '1 rue',
            'city' => 'Douala',
            'surface' => '50',
            'type' => 'apartment',
            'monthly_rent' => 100000,
        ]);
    }

    /** Bien + locataire + contrat actif pour $owner ; renvoie [contrat, user locataire]. */
    protected function lease(User $owner, string $propertyName = 'Bien'): array
    {
        $property = $this->property($owner, $propertyName);
        $property->update(['status' => 'occupied']);
        $tenantUser = $this->user('tenant');
        $tenant = Tenant::create(['user_id' => $tenantUser->id, 'property_id' => $property->id]);
        $contract = Contract::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subMonth(),
            'rent_amount' => 100000,
        ]);

        return [$contract, $tenantUser];
    }

    protected function rentWithPayment(Contract $contract, string $month = '2026-09'): Payment
    {
        $rent = Rent::create([
            'contract_id' => $contract->id,
            'month' => $month,
            'due_date' => "{$month}-05",
            'amount' => 100000,
            'status' => 'paid',
        ]);

        return Payment::create([
            'rent_id' => $rent->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'success',
        ]);
    }

    protected function document(Contract $contract, User $uploader, bool $shared = true): Document
    {
        Storage::disk('local')->put("documents/doc{$contract->id}.pdf", 'pdf');

        return Document::create([
            'contract_id' => $contract->id,
            'uploaded_by' => $uploader->id,
            'uploader_role' => $uploader->role,
            'title' => 'Quittance',
            'type' => 'quittance',
            'file_path' => "documents/doc{$contract->id}.pdf",
            'is_shared' => $shared,
        ]);
    }
}
