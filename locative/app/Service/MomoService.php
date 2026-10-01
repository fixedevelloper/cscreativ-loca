<?php

namespace App\Service;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MomoService
{
    /** Délai max d'un appel à MoMo (secondes) : jamais de worker bloqué indéfiniment. */
    private const TIMEOUT = 15;

    private $baseUrl;
    private $subscriptionKey;
    private $apiUser;
    private $apiKey;
    private $targetEnvironment;

    public function __construct()
    {
        $this->baseUrl = config('services.momo.base_url');
        $this->subscriptionKey = config('services.momo.subscription_key');
        $this->apiUser = config('services.momo.api_user');
        $this->apiKey = config('services.momo.api_key');
        // Une seule valeur pour tous les appels (avant : 'mtncameroon' par
        // défaut pour requestToPay mais 'sandbox' pour le statut).
        $this->targetEnvironment = config('services.momo.env', 'sandbox');
    }

    /**
     * Token d'accès, mis en cache jusqu'à peu avant son expiration (avant :
     * un nouveau token demandé à chaque appel).
     *
     * @throws RuntimeException si MoMo ne délivre pas de token
     */
    public function getToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());
        if ($cached) {
            return $cached;
        }

        $response = Http::timeout(self::TIMEOUT)->withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'Authorization' => 'Basic ' . base64_encode($this->apiUser . ':' . $this->apiKey),
        ])->post($this->baseUrl . '/token/');

        $token = $response->json('access_token');
        if (!$response->successful() || !$token) {
            throw new RuntimeException("Token MoMo indisponible (HTTP {$response->status()}).");
        }

        $ttl = max((int) $response->json('expires_in', 3600) - 60, 60);
        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    /**
     * Demande de paiement envoyée sur le téléphone du payeur.
     *
     * @param string $phone numéro local (sans indicatif pays)
     * @throws RuntimeException si MoMo refuse la demande (la tâche appelante
     *         SendMomoPaymentRequest est alors rejouée)
     */
    public function requestToPay(string $referenceId, string $phone, float $amount, ?string $message = null): int
    {
        $response = Http::timeout(self::TIMEOUT)->withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
            'X-Reference-Id' => $referenceId,
            'X-Target-Environment' => $this->targetEnvironment,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'Content-Type' => 'application/json',
            'X-Callback-Url' => config('services.momo.callback_url') ?: route('momo.callback'),
        ])->post($this->baseUrl . '/v1_0/requesttopay', [
            'amount' => (string) $amount,
            'currency' => config('services.momo.currency', 'XAF'),
            'externalId' => $referenceId,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => config('services.momo.country_code', '237') . $phone,
            ],
            'payerMessage' => $message ?? 'Abonnement ' . config('app.name'),
            'payeeNote' => $message ?? 'Abonnement ' . config('app.name'),
        ]);

        // 🔐 Jamais de token ni de body complet dans les logs.
        logger('Réponse MoMo requestToPay', ['reference' => $referenceId, 'status' => $response->status()]);

        // 202 Accepted attendu ; 409 = référence déjà soumise (rejeu sans effet).
        if (!$response->successful() && $response->status() !== 409) {
            throw new RuntimeException("requestToPay refusé par MoMo (HTTP {$response->status()}).");
        }

        return $response->status();
    }

    /**
     * Statut d'un paiement (source de vérité pour toute activation).
     *
     * @throws RuntimeException si MoMo ne répond pas correctement
     */
    public function getPaymentStatus(string $referenceId): array
    {
        $response = Http::timeout(self::TIMEOUT)->withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
            'X-Target-Environment' => $this->targetEnvironment,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->get($this->baseUrl . '/v1_0/requesttopay/' . $referenceId);

        if (!$response->successful()) {
            throw new RuntimeException("Statut MoMo indisponible (HTTP {$response->status()}).");
        }

        return $response->json() ?? [];
    }

    private function tokenCacheKey(): string
    {
        return 'momo:token:' . md5($this->baseUrl . '|' . $this->apiUser);
    }
}
