<?php

namespace App\Service;

use App\Models\Payment;
use App\Models\SubscriptionPayment;

/**
 * Applique le statut MoMo (source de vérité) à un paiement en attente,
 * qu'il s'agisse d'un abonnement ou d'un loyer. Utilisé par la
 * confirmation manuelle, le webhook et le contrôle périodique
 * (app:get-status-momo) — jamais par confiance dans un statut reçu du client.
 */
class MomoReconciler
{
    public function __construct(
        private readonly MomoService $momo,
        private readonly RentSettlement $settlement,
    ) {
    }

    public function findByReference(string $reference): SubscriptionPayment|Payment|null
    {
        return SubscriptionPayment::where('reference', $reference)->first()
            ?? Payment::where('reference', $reference)->first();
    }

    /**
     * @return string statut résultant : success | failed | pending
     * @throws \RuntimeException si MoMo ne répond pas
     */
    public function reconcile(SubscriptionPayment|Payment $payment): string
    {
        if ($payment->status !== 'pending') {
            return $payment->status;
        }

        $status = $this->momo->getPaymentStatus($payment->reference)['status'] ?? null;

        if ($status === 'SUCCESSFUL') {
            $payment instanceof SubscriptionPayment
                ? $payment->markAsSuccessful()
                : $this->settlement->confirmPending($payment);

            return 'success';
        }

        if ($status === 'FAILED') {
            $payment->update(['status' => 'failed']);

            return 'failed';
        }

        return 'pending';
    }
}
