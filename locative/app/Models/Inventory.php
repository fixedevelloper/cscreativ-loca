<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = [
        'property_id', 'contract_id', 'user_id', 'type', 'general_condition', 'inventory_date'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * Restreint la requête aux états des lieux visibles par $user selon son rôle.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return match ($user->role) {
            'admin' => $query,
            'owner' => $query->whereHas('property', fn ($q) => $q->where('user_id', $user->id)),
            'agent' => $query->whereHas('property', fn ($q) => $q->whereIn('user_id', $user->owners()->pluck('users.id'))),
            'tenant' => $query->whereHas('contract', fn ($q) => $q->whereIn('tenant_id', $user->tenants()->pluck('tenants.id'))),
            default => $query->whereRaw('1 = 0'),
        };
    }
}

