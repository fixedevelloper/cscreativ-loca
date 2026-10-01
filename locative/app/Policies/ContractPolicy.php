<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function view(User $user, Contract $contract): bool
    {
        return match ($user->role) {
            'admin' => true,
            'owner' => $contract->property?->user_id === $user->id,
            'agent' => $user->owners()->whereKey($contract->property?->user_id)->exists(),
            'tenant' => $user->tenants()->whereKey($contract->tenant_id)->exists(),
            default => false,
        };
    }

    public function update(User $user, Contract $contract): bool
    {
        return in_array($user->role, ['admin']) || ($user->role === 'owner' && $contract->property?->user_id === $user->id);
    }

    /** Côté bailleur du bail : bailleur, agent qui le gère (mandataire), admin. */
    public function manageLease(User $user, Contract $contract): bool
    {
        return match ($user->role) {
            'admin' => true,
            'owner' => $contract->property?->user_id === $user->id,
            'agent' => $user->owners()->whereKey($contract->property?->user_id)->exists(),
            default => false,
        };
    }

    /** Partie qui signerait au nom de $user (null : ne peut pas signer). */
    public function signingParty(User $user, Contract $contract): ?string
    {
        if ($user->role === 'tenant') {
            return $user->tenants()->whereKey($contract->tenant_id)->exists() ? 'tenant' : null;
        }

        return $this->manageLease($user, $contract) ? 'landlord' : null;
    }
}
