<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;

class InventoryPolicy
{
    public function view(User $user, Inventory $inventory): bool
    {
        return match ($user->role) {
            'admin' => true,
            'owner' => $inventory->property?->user_id === $user->id,
            'agent' => $user->owners()->whereKey($inventory->property?->user_id)->exists(),
            'tenant' => $user->tenants()->whereKey($inventory->contract?->tenant_id)->exists(),
            default => false,
        };
    }
}
