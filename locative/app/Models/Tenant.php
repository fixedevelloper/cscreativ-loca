<?php


namespace App\Models;

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'property_id',
        'profession',
        'nationality',
        'gender',
        'date_birth',
        'father_name',
        'mother_name',
        'document_type',
        'document_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Restreint la requête aux locataires visibles par $user selon son rôle.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return match ($user->role) {
            'admin' => $query,
            'owner' => $query->whereHas('property', fn ($q) => $q->where('user_id', $user->id)),
            'agent' => $query->whereHas('property', fn ($q) => $q->whereIn('user_id', $user->owners()->pluck('users.id'))),
            'tenant' => $query->where('user_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

}
