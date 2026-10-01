<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Administration de la plateforme (routes du groupe role:admin) :
 * suspension de comptes et suivi des abonnements / revenus SaaS.
 */
class AdminController extends Controller
{
    public function suspend(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return $this->error(__('messages.admin.cannot_suspend_self'), 422);
        }

        $user->forceFill(['suspended_at' => now()])->save();
        // Déconnexion immédiate partout : les tokens existants sont révoqués.
        $user->tokens()->delete();

        return response()->json(['message' => __('messages.admin.suspended'), 'data' => $user]);
    }

    public function reactivate(User $user)
    {
        $user->forceFill(['suspended_at' => null])->save();

        return response()->json(['message' => __('messages.admin.reactivated'), 'data' => $user]);
    }

    /**
     * Abonnements de tous les bailleurs (liste paginée, filtre ?status=,
     * recherche sur le bailleur) + synthèse des revenus.
     */
    public function subscriptions(Request $request)
    {
        $query = Subscription::with('user:id,name,email')->latest();

        if (in_array($request->get('status'), ['active', 'pending', 'expired', 'cancelled'], true)) {
            $query->where('status', $request->get('status'));
        }

        if ($search = $request->get('search')) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%"));
        }

        $list = $this->paginated($query, $request)->getData(true);

        $paid = SubscriptionPayment::where('status', 'success');
        $list['summary'] = [
            'active_by_plan' => Subscription::active()
                ->selectRaw('plan_name, count(*) as total')
                ->groupBy('plan_name')
                ->pluck('total', 'plan_name'),
            'revenue_this_month' => round((float) (clone $paid)
                ->whereYear('updated_at', now()->year)
                ->whereMonth('updated_at', now()->month)
                ->sum('amount'), 2),
            'revenue_total' => round((float) (clone $paid)->sum('amount'), 2),
        ];

        return response()->json($list);
    }
}
