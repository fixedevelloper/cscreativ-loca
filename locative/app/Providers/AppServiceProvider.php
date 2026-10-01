<?php

namespace App\Providers;

use App\Service\Sms\LogSmsSender;
use App\Service\Sms\SmsSender;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Pilote SMS (voir App\Service\Sms\SmsSender) : "log" tant qu'aucun
        // fournisseur n'est branché.
        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            default => new LogSmsSender(),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🔐 Jamais de mode debug en production (traces, chemins, requêtes SQL
        // exposés aux clients), même si APP_DEBUG=true a été oublié.
        if ($this->app->isProduction() && config('app.debug')) {
            config(['app.debug' => false]);
            Log::warning('APP_DEBUG=true ignoré en production : désactivez-le dans .env.');
        }

        // 🔐 Anti brute-force sur le login : 5 tentatives/minute par IP+email.
        // Voir routes/api.php (throttle:login sur POST /login).
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('email')) . '|' . $request->ip();
            return Limit::perMinute(5)->by($key);
        });

        // Le lien de (ré)initialisation du mot de passe pointe vers le
        // frontend (page /auth/reset-password), pas vers une route Laravel
        // 'password.reset' qui n'existe pas dans cette API.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return config('app.frontend_url') . '/auth/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }
}
