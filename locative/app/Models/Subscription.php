<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_name',
        'price',
        'property_limit',
        'tenant_limit',
        'duration_days',
        'start_date',
        'end_date',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Souscriptions actives et non échues (end_date incluse).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->whereDate('end_date', '>=', now());
    }

    /**
     * Le plan est-il plein pour ce type de ressource ? Limite null = illimité
     * (plan Agency) ; 0 = aucune ressource autorisée.
     */
    public function limitReached(string $type, int $currentCount): bool
    {
        $limit = match ($type) {
            'property' => $this->property_limit,
            'tenant' => $this->tenant_limit,
        };

        return $limit !== null && $currentCount >= $limit;
    }

    public function isActive()
    {
        return $this->status === 'active' && ($this->end_date === null || $this->end_date >= now());
    }
}

