<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRequest extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['plumbing', 'electricity', 'appliance', 'structure', 'security', 'other'];
    public const PRIORITIES = ['low', 'normal', 'urgent'];
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    protected $fillable = [
        'property_id', 'contract_id', 'created_by', 'title', 'description',
        'category', 'priority', 'status', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    // Valeurs par défaut aussi côté modèle (réponse de création complète).
    protected $attributes = ['status' => 'open', 'priority' => 'normal', 'category' => 'other'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(MaintenanceComment::class)->oldest();
    }

    /**
     * Demandes visibles par $user : bailleur du bien, agent qui le gère,
     * locataire du bail concerné (ou auteur), admin.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return match ($user->role) {
            'admin' => $query,
            'owner' => $query->whereHas('property', fn ($q) => $q->where('user_id', $user->id)),
            'agent' => $query->whereHas('property', fn ($q) => $q->whereIn('user_id', $user->owners()->pluck('users.id'))),
            'tenant' => $query->where(fn ($q) => $q->where('created_by', $user->id)
                ->orWhereHas('contract', fn ($c) => $c->whereIn('tenant_id', $user->tenants()->pluck('tenants.id')))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Personnes à prévenir d'un événement sur la demande : auteur, bailleur,
     * agents du bailleur, locataire du bail — sauf $except (l'auteur de
     * l'événement).
     */
    public function participants(?User $except = null)
    {
        $this->loadMissing('property.owner.agents', 'contract.tenant.user', 'author');

        return collect([$this->author, $this->property?->owner, $this->contract?->tenant?->user])
            ->concat($this->property?->owner?->agents ?? [])
            ->filter()
            ->unique('id')
            ->reject(fn (User $u) => $except && $u->id === $except->id)
            ->values();
    }
}
