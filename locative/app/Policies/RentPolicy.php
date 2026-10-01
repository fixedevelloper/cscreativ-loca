<?php

namespace App\Policies;

use App\Models\Rent;
use App\Models\User;

class RentPolicy
{
    /** Paiement en ligne : uniquement le locataire titulaire du bail. */
    public function pay(User $user, Rent $rent): bool
    {
        return $user->role === 'tenant'
            && $user->tenants()->whereKey($rent->contract?->tenant_id)->exists();
    }
}
