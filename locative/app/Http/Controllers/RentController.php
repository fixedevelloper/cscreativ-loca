<?php

namespace App\Http\Controllers;

use App\Http\Resources\RentResource;
use App\Models\Contract;
use App\Models\Rent;
use Illuminate\Http\Request;

class RentController extends Controller
{
    public function index(Request $request)
    {
        $query = Rent::with(['contract.property', 'contract.tenant.user', 'receipt'])
            ->withSum(['payments as paid_amount' => fn ($q) => $q->where('status', 'success')], 'amount')
            ->visibleTo(auth()->user())
            ->latest('due_date');

        // 🔹 Filtre recherche : mois, bien ou nom du locataire
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('month', 'like', "%$search%")
                    ->orWhereHas('contract.property', fn ($p) => $p->where('name', 'like', "%$search%"))
                    ->orWhereHas('contract.tenant.user', fn ($u) => $u->where('name', 'like', "%$search%"));
            });
        }

        // 🔹 ?overdue=1 : échéances dépassées non soldées (dashboard, relances)
        if ($request->boolean('overdue')) {
            $query->whereIn('status', ['pending', 'late'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString());
        }

        // 🔹 ?status=paid|pending|late
        if (in_array($request->get('status'), ['paid', 'pending', 'late'], true)) {
            $query->where('status', $request->get('status'));
        }

        return $this->paginated($query, $request, RentResource::class);
    }

    public function rent_to_contract(Request $request, $id)
    {
        // 🔐 Vérifie que le contrat demandé est bien visible par l'utilisateur
        $this->authorize('view', Contract::findOrFail($id));

        $query = Rent::with(['contract.property', 'contract.tenant.user', 'receipt'])
            ->withSum(['payments as paid_amount' => fn ($q) => $q->where('status', 'success')], 'amount')
            ->where('contract_id', $id)
            ->latest('due_date');

        return $this->paginated($query, $request, RentResource::class);
    }
}
