<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    /** Voir et commenter : mêmes règles que MaintenanceRequest::scopeVisibleTo. */
    public function view(User $user, MaintenanceRequest $request): bool
    {
        return MaintenanceRequest::whereKey($request->id)->visibleTo($user)->exists();
    }

    public function comment(User $user, MaintenanceRequest $request): bool
    {
        return $this->view($user, $request);
    }

    /**
     * Changer le statut : côté gestion (bailleur, agent, admin). Le locataire
     * peut seulement clore sa propre demande une fois résolue.
     */
    public function updateStatus(User $user, MaintenanceRequest $request, ?string $status = null): bool
    {
        if ($user->role === 'tenant') {
            return $status === 'closed' && $request->created_by === $user->id && $request->status === 'resolved';
        }

        return $this->view($user, $request);
    }
}
