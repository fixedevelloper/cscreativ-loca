<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'rent_id', 'amount', 'method', 'status', 'reference', 'payer_phone'];

    public function rent()
    {
        return $this->belongsTo(Rent::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Restreint la requête aux paiements visibles par $user selon son rôle.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return match ($user->role) {
            'admin' => $query,
            'owner' => $query->whereHas('rent.contract.property', fn ($q) => $q->where('user_id', $user->id)),
            'agent' => $query->whereHas('rent.contract.property', fn ($q) => $q->whereIn('user_id', $user->owners()->pluck('users.id'))),
            'tenant' => $query->whereHas('rent.contract', fn ($q) => $q->whereIn('tenant_id', $user->tenants()->pluck('tenants.id'))),
            default => $query->whereRaw('1 = 0'),
        };
    }
}

