<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Règle de visibilité/propriété centralisée, utilisée à la fois pour
     * autoriser un accès unitaire (view/update/delete) ET pour construire
     * le scope de liste (voir Property::scopeVisibleTo).
     */
    public function view(User $user, Property $property): bool
    {
        return match ($user->role) {
            'admin' => true,
            'owner' => $property->user_id === $user->id,
            'agent' => $user->owners()->whereKey($property->user_id)->exists(),
            default => false,
        };
    }

    public function update(User $user, Property $property): bool
    {
        return $this->view($user, $property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->role === 'admin' || $property->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'agent', 'admin']);
    }

    /**
     * Créer un bien AU NOM de $owner : soi-même (bailleur), un bailleur
     * rattaché (agent) ou n'importe quel bailleur (admin).
     */
    public function createFor(User $user, User $owner): bool
    {
        return match ($user->role) {
            'admin' => true,
            'owner' => $owner->id === $user->id,
            'agent' => $user->owners()->whereKey($owner->id)->exists(),
            default => false,
        };
    }
}
