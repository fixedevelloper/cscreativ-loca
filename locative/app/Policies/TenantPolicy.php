<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return match ($user->role) {
            'admin' => true,
            'owner' => $tenant->property?->user_id === $user->id,
            'agent' => $user->owners()->whereKey($tenant->property?->user_id)->exists(),
            'tenant' => $tenant->user_id === $user->id,
            default => false,
        };
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->view($user, $tenant) && $user->role !== 'tenant';
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'owner' && $tenant->property?->user_id === $user->id);
    }
}
