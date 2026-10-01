<?php

namespace App\Http\Controllers;

use App\Exceptions\RentPaymentException;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\RentResource;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Rent;
use App\Service\RentSettlement;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['rent.contract.property.owner', 'rent.contract.tenant.user'])
            ->visibleTo(auth()->user())
            ->latest();

        if ($search = $request->get('search')) {
            // rents n'a pas de colonne "reference" ni de relation "property" :
            // on cherche sur le mois du loyer, le bien et le locataire.
            $query->where(function ($q) use ($search) {
                $q->whereHas('rent', fn ($r) => $r->where('month', 'like', "%$search%"))
                    ->orWhereHas('rent.contract.property', fn ($p) => $p->where('name', 'like', "%$search%")
                        ->orWhere('city', 'like', "%$search%")
                        ->orWhere('address', 'like', "%$search%"))
                    ->orWhereHas('rent.contract.tenant.user', fn ($u) => $u->where('name', 'like', "%$search%"));
            });
        }

        return $this->paginated($query, $request, PaymentResource::class);
    }

    public function payment_to_contract(Request $request, $id)
    {
        // 🔐 Vérifie que le contrat demandé est bien visible par l'utilisateur
        // (corrige un IDOR qui permettait de lire les paiements de n'importe
        // quel contrat en devinant son id).
        $this->authorize('view', Contract::findOrFail($id));

        $query = Payment::with(['rent.contract.property.owner', 'rent.contract.tenant.user'])
            ->whereHas('rent', fn ($q) => $q->where('contract_id', $id))
            ->latest();

        return $this->paginated($query, $request, PaymentResource::class);
    }

    /**
     * Encaissement manuel d'un loyer (bailleur, agent, admin). Les règles
     * (plafond du reste dû, solde, quittance) sont dans RentSettlement,
     * partagées avec le paiement en ligne du locataire.
     */
    public function store(StorePaymentRequest $request, RentSettlement $settlement)
    {
        $data = $request->validated();

        try {
            $payment = $settlement->record((int) $data['rent_id'], (float) $data['amount'], $data['method'], auth()->id());
        } catch (RentPaymentException $e) {
            return $this->error($e->translated(), 422);
        }

        return response()->json([
            'message' => __('messages.payments.recorded'),
            'data' => new PaymentResource($payment->load('rent')),
        ], 201);
    }

}
