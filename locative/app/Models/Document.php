<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'rent_id',
        'uploaded_by',
        'uploader_role',
        'title',
        'type',
        'description',
        'file_path',
        'is_shared',
        'shared_with',
        'shared_at',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'shared_with' => 'array',
        'shared_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function rent()
    {
        return $this->belongsTo(Rent::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Restreint la requête aux documents visibles par $user selon son rôle.
     * Doit rester aligné avec DocumentPolicy::view.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->role === 'admin') {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('uploaded_by', $user->id);

            match ($user->role) {
                'owner' => $q->orWhereHas('contract.property', fn ($p) => $p->where('user_id', $user->id)),
                'agent' => $q->orWhereHas('contract.property', fn ($p) => $p->whereIn('user_id', $user->owners()->pluck('users.id'))),
                'tenant' => $q->orWhere(fn ($s) => $s->where('is_shared', true)
                    ->whereHas('contract', fn ($c) => $c->whereIn('tenant_id', $user->tenants()->pluck('tenants.id')))),
                default => null,
            };
        });
    }
}

