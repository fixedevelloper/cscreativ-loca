<?php


namespace App\Models;


namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'month',
        'due_date',
        'amount',
        'status',
        'late_penalty',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /** Quittance PDF générée au solde du loyer (voir GenerateRentReceiptPdf). */
    public function receipt()
    {
        return $this->hasOne(Document::class)->where('type', 'quittance');
    }

    public function reminders()
    {
        return $this->hasMany(RentReminder::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date && Carbon::parse($this->due_date)->isPast();
    }

    public function daysLate(): int
    {
        if (!$this->due_date) {
            return 0;
        }

        return max(0, Carbon::parse($this->due_date)->diffInDays(now(), false));
    }

    /**
     * Montant total dû (loyer + pénalité déjà appliquée, si il y en a une).
     */
    public function totalDue(): float
    {
        return (float) $this->amount + (float) ($this->late_penalty ?? 0);
    }

    /**
     * Calcule la pénalité applicable selon config('rent'), sans la persister
     * (utilisé par SendRentReminders pour décider s'il faut la figer).
     */
    public function calculatePenalty(): float
    {
        $graceDays = config('rent.penalty_grace_days');

        if ($this->daysLate() < $graceDays) {
            return 0;
        }

        return round((float) $this->amount * config('rent.penalty_rate'), 2);
    }

    /**
     * Restreint la requête aux loyers visibles par $user selon son rôle.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return match ($user->role) {
            'admin' => $query,
            'owner' => $query->whereHas('contract.property', fn ($q) => $q->where('user_id', $user->id)),
            'agent' => $query->whereHas('contract.property', fn ($q) => $q->whereIn('user_id', $user->owners()->pluck('users.id'))),
            'tenant' => $query->whereHas('contract', fn ($q) => $q->whereIn('tenant_id', $user->tenants()->pluck('tenants.id'))),
            default => $query->whereRaw('1 = 0'),
        };
    }
}


