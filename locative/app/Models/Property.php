<?php


namespace App\Models;

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'city',
        'surface',
        'number_piece',
        'monthly_charge',
        'type',
        'monthly_rent',
        // Statut d'occupation (free | occupied | maintenance), mis à jour par
        // TenantController et app:expire-contracts. Absent jusqu'ici : chaque
        // update(['status' => ...]) était ignoré en silence. Jamais accepté
        // depuis la requête (StorePropertyRequest ne le valide pas).
        'status',
    ];

    /** Valeur par défaut aussi côté modèle (sinon null dans la réponse de création). */
    protected $attributes = [
        'status' => 'free',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Restreint la requête aux biens visibles par $user selon son rôle.
     * Centralise une règle auparavant dupliquée dans chaque contrôleur.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return match ($user->role) {
            'admin' => $query,
            'owner' => $query->where('user_id', $user->id),
            'agent' => $query->whereIn('user_id', $user->owners()->pluck('users.id')),
            default => $query->whereRaw('1 = 0'),
        };
    }
}

