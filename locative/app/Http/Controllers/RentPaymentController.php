<?php

namespace App\Http\Controllers;

use App\Exceptions\RentPaymentException;
use App\Jobs\SendMomoPaymentRequest;
use App\Models\Payment;
use App\Models\Rent;
use App\Service\MomoReconciler;
use App\Service\RentSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Paiement du loyer en ligne par le locataire (MTN Mobile Money).
 * 1. initiate : demande de paiement envoyée sur le téléphone (reste dû) ;
 * 2. confirm  : statut vérifié auprès de MoMo (aussi fait par le webhook et
 *    par app:get-status-momo). À la confirmation, RentSettlement applique
 *    les mêmes règles qu'un encaissement manuel (solde, quittance).
 */
class RentPaymentController extends Controller
{
    /** Délai pendant lequel une demande en attente bloque une nouvelle demande. */
    private const PENDING_WINDOW_MINUTES = 15;

    public function initiate(Request $request, Rent $rent, RentSettlement $settlement)
    {
        $this->authorize('pay', $rent);

        $data = $request->validate([
            'phone' => ['required', 'regex:/^\d{9}$/'],
        ]);

        $amount = $settlement->remaining($rent);
        try {
            $settlement->assertPayable($rent, $amount);
        } catch (RentPaymentException $e) {
            return $this->error($e->translated(), 422);
        }

        // Pas de double débit : une demande déjà en cours doit d'abord aboutir.
        $pending = $rent->payments()->where('status', 'pending')->whereNotNull('reference')
            ->where('created_at', '>', now()->subMinutes(self::PENDING_WINDOW_MINUTES))
            ->exists();
        if ($pending || $amount <= 0) {
            return $this->error(__('messages.rent_payment.pending_exists'), 409);
        }

        $payment = Payment::create([
            'rent_id' => $rent->id,
            'user_id' => auth()->id(),
            'amount' => $amount,
            'method' => 'mobile',
            'status' => 'pending',
            'reference' => (string) Str::uuid(),
            'payer_phone' => $data['phone'],
        ]);

        SendMomoPaymentRequest::dispatch(
            $payment->reference,
            $data['phone'],
            (float) $amount,
            __('messages.rent_payment.momo_message', ['month' => $rent->month]),
        );

        return response()->json([
            'message' => __('messages.rent_payment.requested'),
            'data' => ['reference' => $payment->reference, 'amount' => (float) $amount],
        ], 202);
    }

    public function confirm(string $reference, MomoReconciler $reconciler)
    {
        // 🔐 Seul le locataire qui a initié le paiement peut le vérifier.
        $payment = Payment::where('reference', $reference)->where('user_id', auth()->id())->firstOrFail();

        try {
            $status = $reconciler->reconcile($payment);
        } catch (\RuntimeException $e) {
            report($e);
            return $this->error(__('messages.subscriptions.provider_unavailable'), 503);
        }

        $rent = $payment->rent()->first();

        return response()->json([
            'message' => __("messages.rent_payment.status_{$status}"),
            'data' => [
                'status' => $status,
                'rent_status' => $rent->status,
                'remaining' => app(RentSettlement::class)->remaining($rent),
            ],
        ]);
    }
}
