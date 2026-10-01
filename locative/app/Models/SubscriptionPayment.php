<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_name',
        'amount',
        'reference',
        'payment_method',
        'status',
        'gateway_response',
        'subscription_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Paiement confirmé par MoMo : active la souscription liée et, seulement
     * maintenant, fait expirer l'ancienne souscription active (un upgrade dont
     * le paiement échoue ne doit pas couper l'accès en cours).
     */
    public function markAsSuccessful(): void
    {
        DB::transaction(function () {
            $this->update(['status' => 'success']);

            Subscription::where('user_id', $this->user_id)
                ->where('id', '!=', $this->subscription_id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $this->subscription()->update(['status' => 'active']);
        });
    }
}
