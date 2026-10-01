<?php

namespace App\Http\Controllers;

use App\Service\MomoReconciler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MomoCallbackController extends Controller
{
    /**
     * Webhook public appelé par MTN MoMo (X-Callback-Url de MomoService::requestToPay).
     *
     * ⚠️ Ce endpoint est nécessairement public (MoMo n'a pas de token Sanctum),
     * donc on ne fait JAMAIS confiance au contenu du body pour décider d'un
     * succès : on ré-interroge activement l'API MoMo (source de vérité) à
     * partir de la référence reçue avant d'activer quoi que ce soit.
     */
    public function callback(Request $request, MomoReconciler $reconciler)
    {
        $referenceId = $request->input('referenceId') ?? $request->input('externalId');

        if (!$referenceId) {
            Log::warning('Callback MoMo reçu sans référence exploitable', $request->all());
            return $this->error('Référence manquante', 400);
        }

        // Abonnement ou loyer : même référence MoMo (X-Reference-Id).
        $payment = $reconciler->findByReference($referenceId);

        if (!$payment) {
            Log::warning("Callback MoMo pour une référence inconnue: {$referenceId}");
            return $this->error('Paiement non trouvé', 404);
        }

        try {
            $reconciler->reconcile($payment); // idempotent si déjà confirmé
        } catch (\RuntimeException $e) {
            // Re-vérification impossible : on n'active rien. Le filet
            // app:get-status-momo reprendra ce paiement.
            report($e);
            return $this->error('Vérification indisponible', 503);
        }

        return response()->json(['message' => 'Callback traité']);
    }
}
