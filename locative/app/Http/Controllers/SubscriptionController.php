<?php


namespace App\Http\Controllers;

use App\Http\Requests\ConfirmSubscriptionPaymentRequest;
use App\Http\Requests\InitiateSubscriptionPaymentRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Jobs\SendMomoPaymentRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Service\MomoReconciler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Catalogue public des plans (source de vérité : config/plans.php). Le
     * frontend l'affiche tel quel au lieu de maintenir sa propre copie.
     */
    public function plans()
    {
        $plans = collect(config('plans'))
            ->map(fn (array $plan, string $key) => ['key' => $key] + $plan)
            ->values();

        // Les prix de config/plans.php sont exprimés en XAF.
        return response()->json(['data' => $plans, 'currency' => 'XAF']);
    }

    public function index()
    {
        $subscriptions = Subscription::where('user_id', auth()->id())->get();
        return response()->json(['data' => $subscriptions]);
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $user = auth()->user();
        $plans = config('plans');
        $data = $request->validated();
        $plan = $plans[$data['plan_name']];

        DB::beginTransaction();
        try {
            // 🔸 Abandonne les souscriptions en attente de paiement. L'éventuelle
            //    souscription active reste en place jusqu'à confirmation du
            //    paiement (voir SubscriptionPayment::markAsSuccessful).
            Subscription::where('user_id', $user->id)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            // 🔹 Création de la nouvelle souscription (pending jusqu’au paiement)
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_name' => $data['plan_name'],
                'price' => $plan['price'],
                'property_limit' => $plan['property_limit'],
                'tenant_limit' => $plan['tenant_limit'],
                'start_date' => now(),
                'end_date' => now()->addDays($plan['duration_days']),
                'status' => 'pending',
            ]);

            // 🔹 Enregistrement du paiement lié à la souscription
            $payment = SubscriptionPayment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_name' => $data['plan_name'],
                'amount' => $plan['price'],
                'status' => 'pending',
                'reference' => (string) Str::uuid(),
            ]);

            // 🔹 Plan gratuit : activation immédiate, pas de paiement à attendre
            if ($plan['price'] == 0) {
                $payment->markAsSuccessful();
            }

            DB::commit();

            return response()->json([
                'message' => __('messages.subscriptions.created'),
                'data' => [
                    'subscription_id' => $subscription->id,
                    'reference' => $payment->reference,
                    'requires_payment' => $plan['price'] > 0,
                ]
            ], 201);
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception; // rendu générique par le gestionnaire d'exceptions commun
        }
    }



    public function cancel($id)
    {
        $subscription = Subscription::where('user_id', auth()->id())->findOrFail($id);
        $subscription->update(['status' => 'cancelled']);

        return response()->json(['message' => __('messages.subscriptions.cancelled')]);
    }

    /**
     * Déclenche la demande de paiement mobile money (MoMo) pour un paiement
     * d'abonnement en attente appartenant à l'utilisateur authentifié.
     */
    public function initiatePayment(InitiateSubscriptionPaymentRequest $request)
    {
        $data = $request->validated();

        $payment = SubscriptionPayment::where('reference', $data['reference'])
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();

        // Appel externe potentiellement lent : envoyé en tâche de fond, la
        // réponse HTTP n'attend pas MoMo (voir App\Jobs\SendMomoPaymentRequest).
        SendMomoPaymentRequest::dispatch($payment->reference, $data['phone'], (float) $payment->amount);

        return response()->json([
            'message' => __('messages.subscriptions.payment_requested'),
            'data' => ['reference' => $payment->reference],
        ]);
    }


    /**
     * Vérifie et confirme l'état d'un paiement d'abonnement en interrogeant
     * ACTIVEMENT MoMo (source de vérité), au lieu de faire confiance à un
     * statut envoyé par le client. Remplace l'ancien /callback qui acceptait
     * n'importe quel statut ('success') pour n'importe quelle référence.
     */
    public function confirm(ConfirmSubscriptionPaymentRequest $request, MomoReconciler $reconciler)
    {
        $data = $request->validated();

        // 🔐 Scope obligatoire : un utilisateur ne peut vérifier que SES propres paiements
        $payment = SubscriptionPayment::where('reference', $data['reference'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($payment->status === 'success') {
            return response()->json([
                'message' => __('messages.subscriptions.already_confirmed'),
                'data' => ['payment' => $payment, 'subscription' => $payment->subscription],
            ]);
        }

        try {
            $reconciler->reconcile($payment);
        } catch (\RuntimeException $e) {
            report($e);
            return $this->error(__('messages.subscriptions.provider_unavailable'), 503);
        }

        return response()->json([
            'message' => __('messages.subscriptions.status_checked'),
            'data' => [
                'payment' => $payment->fresh(),
                'subscription' => $payment->subscription()->first(),
            ],
        ]);
    }
}

