<?php

namespace App\Service;

use App\Exceptions\RentPaymentException;
use App\Jobs\GenerateRentReceiptPdf;
use App\Models\Payment;
use App\Models\Rent;
use App\Notifications\RentPaymentReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Règles d'encaissement d'un loyer, communes à l'encaissement manuel
 * (bailleur/agent) et au paiement en ligne du locataire (MoMo) :
 * verrou sur le loyer, plafond du reste dû, passage à "payé" et quittance
 * générée une seule fois, au paiement qui solde le loyer.
 */
class RentSettlement
{
    /** Reste dû (loyer + pénalité - encaissements réussis). */
    public function remaining(Rent $rent): float
    {
        $paid = (float) $rent->payments()->where('status', 'success')->sum('amount');

        return max(round($rent->totalDue() - $paid, 2), 0);
    }

    /**
     * Encaissement immédiat (espèces, virement, mobile constaté).
     *
     * @throws RentPaymentException
     */
    public function record(int $rentId, float $amount, string $method, ?int $recordedBy): Payment
    {
        [$payment, $settled] = DB::transaction(function () use ($rentId, $amount, $method, $recordedBy) {
            $rent = Rent::lockForUpdate()->findOrFail($rentId);
            $this->assertPayable($rent, $amount);

            $payment = Payment::create([
                'rent_id' => $rent->id,
                'user_id' => $recordedBy,
                'amount' => $amount,
                'method' => $method,
                'status' => 'success',
            ]);

            return [$payment, $this->settleIfPaid($rent)];
        });

        if ($settled) {
            GenerateRentReceiptPdf::dispatch($payment->rent_id);
        }
        $this->notifyOwner($payment, $recordedBy);

        return $payment;
    }

    /**
     * Vérifie qu'un paiement de $amount est encore possible sur $rent.
     *
     * @throws RentPaymentException
     */
    public function assertPayable(Rent $rent, float $amount): void
    {
        if ($rent->status === 'paid') {
            throw new RentPaymentException('messages.payments.rent_already_paid');
        }

        $remaining = $this->remaining($rent);
        if ($amount > $remaining) {
            throw new RentPaymentException('messages.payments.amount_exceeds_remaining', ['remaining' => $remaining]);
        }
    }

    /**
     * Paiement MoMo confirmé par l'opérateur : l'argent est reçu, il est donc
     * toujours enregistré (même si un autre paiement a soldé le loyer entre
     * temps, auquel cas le trop-perçu est journalisé pour régularisation).
     */
    public function confirmPending(Payment $payment): void
    {
        $settled = DB::transaction(function () use ($payment) {
            $rent = Rent::lockForUpdate()->findOrFail($payment->rent_id);
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'success') {
                return false; // idempotent (webhook + polling)
            }

            if ($payment->amount > $this->remaining($rent)) {
                Log::warning('Trop-perçu sur un loyer payé par MoMo', [
                    'rent_id' => $rent->id, 'payment_id' => $payment->id, 'reference' => $payment->reference,
                ]);
            }

            $payment->update(['status' => 'success']);

            return $this->settleIfPaid($rent);
        });

        if ($settled) {
            GenerateRentReceiptPdf::dispatch($payment->rent_id);
        }
        $this->notifyOwner($payment->fresh(), null);
    }

    /** Passe le loyer à "payé" s'il est soldé ; true si c'est ce paiement qui le solde. */
    private function settleIfPaid(Rent $rent): bool
    {
        if ($rent->status !== 'paid' && $this->remaining($rent) <= 0) {
            $rent->update(['status' => 'paid']);

            return true;
        }

        return false;
    }

    /** Prévient le bailleur d'un paiement reçu, sauf s'il l'a saisi lui-même. */
    private function notifyOwner(Payment $payment, ?int $recordedBy): void
    {
        $payment->load('rent.contract.property.owner', 'rent.contract.tenant.user');
        $owner = $payment->rent->contract?->property?->owner;

        if ($owner && $owner->id !== $recordedBy) {
            $owner->notify(new RentPaymentReceived($payment));
        }
    }
}
