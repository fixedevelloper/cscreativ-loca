<?php

namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\Password;

class User extends Authenticatable implements HasLocalePreference
{
    // 🔒 SoftDeletes : un utilisateur ayant des biens/contrats/paiements liés
    // ne doit jamais être supprimé physiquement (voir contraintes onDelete
    // restrict dans la migration) — la suppression "logique" est la seule
    // opération permise depuis l'application.
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'address', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    // suspended_at volontairement hors $fillable : modifié uniquement par
    // AdminController::suspend / reactivate.
    protected $casts = [
        'suspended_at' => 'datetime',
    ];

    /**
     * Langue des notifications (invitation, relances…) : Laravel l'applique
     * automatiquement à tout ->notify() sur cet utilisateur.
     */
    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.locale');
    }

    /**
     * Envoie l'invitation à définir son mot de passe (broker "invitations",
     * lien valable 72 h). Un échec d'envoi est journalisé sans être bloquant :
     * l'utilisateur peut toujours passer par "mot de passe oublié".
     */
    public function sendInvitation(): void
    {
        try {
            $token = Password::broker('invitations')->createToken($this);
            $this->notify(new AccountInvitation($token));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Souscription active la plus récente de l'utilisateur lui-même.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->active()->latest('end_date')->first();
    }

// Agents reliés à des propriétaires
    public function owners()
    {
        return $this->belongsToMany(User::class, 'agent_owner', 'agent_id', 'owner_id');
    }

    public function agents()
    {
        return $this->belongsToMany(User::class, 'agent_owner', 'owner_id', 'agent_id');
    }



}

