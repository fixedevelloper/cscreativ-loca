<?php


namespace App\Http\Controllers;


use App\Models\SubscriptionPayment;

class FrontController extends Controller
{

    /**
     * Simule une page de paiement (pour test)
     */
    public function mockPaymentApi($ref)
    {
        $payment = SubscriptionPayment::where('reference', $ref)->firstOrFail();

        return response()->json([
            'message' => 'Page de paiement simulée.',
            'data' => [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'plan' => $payment->plan_name,
                'status' => $payment->status,
                'confirm_url' => route('payment.confirm', ['ref' => $payment->reference]),
            ]
        ]);
    }
    public function mockPayment($ref)
    {
        $payment = SubscriptionPayment::where('reference', $ref)->firstOrFail();

        return view('payment.mock', compact('payment'));
    }


    /**
     * Confirme le paiement (callback ou validation manuelle)
     */
    public function confirmPayment($ref)
    {
        $payment = SubscriptionPayment::where('reference', $ref)->firstOrFail();
        $subscription = $payment->subscription;

        // Si déjà payé
        if ($payment->status === 'success') {
            return response()->json(['message' => 'Paiement déjà confirmé.']);
        }

        // ✅ Simule la réussite du paiement
        $payment->update(['status' => 'success']);

        // ✅ Active la souscription
        $subscription->update(['status' => 'active']);

        return response()->json([
            'message' => 'Paiement confirmé avec succès. La souscription est maintenant active.',
            'data' => [
                'subscription' => $subscription,
                'payment' => $payment,
            ]
        ]);
    }
}
