<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'start_date',
        'end_date',
        'number_person',
        'rent_amount',
        'deposit_amount',
        'terms',
        'status',
        'lease_document_id',
        'signed_document_id',
        'signed_at',
    ];

    protected $casts = ['signed_at' => 'datetime'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function leaseDocument()
    {
        return $this->belongsTo(Document::class, 'lease_document_id');
    }

    public function signedDocument()
    {
        return $this->belongsTo(Document::class, 'signed_document_id');
    }

    public function signatures()
    {
        return $this->hasMany(ContractSignature::class);
    }

    public function rents()
    {
        return $this->hasMany(Rent::class);
    }

    /**
     * Restreint la requête aux contrats visibles par $user selon son rôle.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return match ($user->role) {
            'admin' => $query,
            'owner' => $query->whereHas('property', fn ($q) => $q->where('user_id', $user->id)),
            'agent' => $query->whereHas('property', fn ($q) => $q->whereIn('user_id', $user->owners()->pluck('users.id'))),
            'tenant' => $query->whereIn('tenant_id', $user->tenants()->pluck('tenants.id')),
            default => $query->whereRaw('1 = 0'),
        };
    }
}

