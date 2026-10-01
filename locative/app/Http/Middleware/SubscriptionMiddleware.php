<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionMiddleware
{
    /**
     * Vérifie si l'utilisateur possède un abonnement valide et autorisé.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $plan  (optionnel : 'Pro', 'Agency', etc.)
     */
    public function handle(Request $request, Closure $next, $plan = null)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => __('messages.auth.unauthenticated')], 401);
        }

        // 🔹 Les admins passent toujours
        if ($user->role === 'admin') {
            return $next($request);
        }

        // 🔹 Locataire : n'a jamais d'abonnement propre (c'est son bailleur qui
        //    paie). Ses données sont filtrées par les scopes visibleTo et ses
        //    écritures bornées par les policies. Exclu des fonctionnalités
        //    réservées à un plan.
        if ($user->role === 'tenant') {
            return $plan
                ? response()->json(['status' => 'error', 'message' => __('messages.subscriptions.feature_unavailable')], 403)
                : $next($request);
        }

        // 🔹 Agent : couvert par son propre abonnement ou par celui de l'un
        //    des bailleurs qu'il gère.
        if ($user->role === 'agent') {
            $activeSubscription = \App\Models\Subscription::active()
                ->whereIn('user_id', $user->owners()->pluck('users.id')->push($user->id))
                ->when($plan, fn ($q) => $q->where('plan_name', strtolower($plan)))
                ->latest('end_date')
                ->first();
        } else {
            $activeSubscription = $user->activeSubscription();
        }

        if (!$activeSubscription) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.subscriptions.required'),
                // Lu par le frontend pour rediriger vers /subscriptions (un 403
                // d'autorisation ordinaire ne doit pas y renvoyer).
                'code' => 'subscription_required',
            ], 403);
        }

        // 🔹 Vérifie le plan si nécessaire
        if ($plan && strtolower($activeSubscription->plan_name) !== strtolower($plan)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.subscriptions.plan_forbidden', ['plan' => $activeSubscription->plan_name])
            ], 403);
        }

        return $next($request);
    }
}

