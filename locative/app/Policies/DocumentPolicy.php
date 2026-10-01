<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Accès unitaire aux documents. Doit rester aligné avec
 * Document::scopeVisibleTo (règle de liste).
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($user->role === 'admin' || $document->uploaded_by === $user->id) {
            return true;
        }

        $contract = $document->contract;
        if (!$contract) {
            return false;
        }

        return match ($user->role) {
            'owner' => $contract->property?->user_id === $user->id,
            'agent' => $user->owners()->whereKey($contract->property?->user_id)->exists(),
            // 🔐 is_shared ne suffit pas : le document doit aussi concerner
            // un contrat du locataire (sinon fuite des quittances de tous).
            'tenant' => $document->is_shared
                && $user->tenants()->whereKey($contract->tenant_id)->exists(),
            default => false,
        };
    }

    public function share(User $user, Document $document): bool
    {
        if ($user->role === 'admin' || $document->uploaded_by === $user->id) {
            return true;
        }

        $ownerId = $document->contract?->property?->user_id;

        return match ($user->role) {
            'owner' => $ownerId === $user->id,
            'agent' => $user->owners()->whereKey($ownerId)->exists(),
            default => false,
        };
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->role === 'admin' || $document->uploaded_by === $user->id;
    }
}
