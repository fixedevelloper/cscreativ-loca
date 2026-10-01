<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $query = Tenant::with(['user', 'property'])->visibleTo(auth()->user());

        // 🔹 Filtre recherche : identité du locataire (table users) ou bien loué
        //    (tenants n'a pas de colonnes name/city/address).
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%"))
                    ->orWhereHas('property', fn ($p) => $p->where('name', 'like', "%$search%"));
            });
        }

        return $this->paginated($query, $request, TenantResource::class);
    }

    public function store(StoreTenantRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $userAuth = auth()->user(); // 🔐 Utilisateur connecté

            // 1️⃣ L'appartenance du bien à l'utilisateur (bailleur ou agent
            //    qui le gère) est déjà vérifiée par StoreTenantRequest::authorize().
            $property = Property::findOrFail($validated['property_id']);

            // Contrôle le plus spécifique d'abord : un bien occupé est refusé
            // quel que soit l'état de l'abonnement.
            if ($property->status === 'occupied') {
                DB::rollBack();
                return $this->error(__('messages.tenants.property_occupied'), 409);
            }

            // 2️⃣ L'abonnement qui compte est celui du bailleur du bien (un
            //    agent n'a pas d'abonnement propre). Les admins en sont exemptés.
            //    La limite de locataires du plan s'applique au parc du bailleur.
            if ($userAuth->role !== 'admin') {
                $ownerSubscription = $property->owner?->activeSubscription();

                if (!$ownerSubscription) {
                    DB::rollBack();
                    return $this->error(__('messages.tenants.owner_subscription_inactive'), 403);
                }

                $ownerTenantCount = Tenant::whereHas('property', fn ($q) => $q->where('user_id', $property->user_id))->count();
                if ($ownerSubscription->limitReached('tenant', $ownerTenantCount)) {
                    DB::rollBack();
                    return $this->error(__('messages.tenants.limit_reached'), 403);
                }
            }

            // 3️⃣ Créer le User (locataire) avec un mot de passe aléatoire et
            //    inutilisable : le bailleur ne doit jamais connaître le mot
            //    de passe du compte de son locataire.
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make(Str::random(40)),
                'role' => 'tenant',
                'locale' => app()->getLocale(),
            ]);

            // 4️⃣ Créer le Tenant associé
            $tenant = Tenant::create([
                'user_id' => $user->id,
                'property_id' => $validated['property_id'],
                'profession' => $validated['profession'] ?? null,
                'nationality' => $validated['nationality'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'date_birth' => $validated['date_birth'] ?? null,
                'father_name' => $validated['father_name'] ?? null,
                'mother_name' => $validated['mother_name'] ?? null,
                'document_type' => $validated['document_type'] ?? null,
                'document_id' => $validated['document_id'] ?? null,
            ]);

            // 5️⃣ Créer le contrat
            $contract = Contract::create([
                'tenant_id' => $tenant->id,
                'property_id' => $validated['property_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'number_person' => $validated['number_person'] ?? 1,
                'rent_amount' => $validated['rent_amount'],
                'deposit_amount' => $validated['deposit_amount'] ?? 0,
                'terms' => $validated['terms'] ?? null,
            ]);

            // 6️⃣ Mettre à jour le statut du bien
            $property->update(['status' => 'occupied']);

            DB::commit();

            // 7️⃣ Invitation à définir son mot de passe (lien 72 h, dans la
            //    langue de la requête). Après le commit : jamais d'invitation
            //    pour un compte annulé par un rollback.
            $user->sendInvitation();

            return response()->json([
                'message' => __('messages.tenants.created'),
                'tenant' => $tenant->load('user', 'property'),
                'contract' => $contract,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e; // rendu par le gestionnaire d'exceptions commun (bootstrap/app.php)
        }

    }

    /**
     * "Supprime" un locataire : fin de bail plutôt qu'effacement. Les contrats
     * actifs sont résiliés, le bien redevient libre et la fiche locataire est
     * soft-deletée — baux, loyers et quittances restent conservés (obligation
     * de conservation légale), et le compte utilisateur n'est pas supprimé.
     */
    public function destroy(Tenant $tenant)
    {
        $this->authorize('delete', $tenant);

        DB::transaction(function () use ($tenant) {
            $tenant->contracts()->where('status', 'active')->get()->each(function (Contract $contract) {
                $contract->update([
                    'status' => 'terminated',
                    'end_date' => $contract->end_date && now()->gt($contract->end_date)
                        ? $contract->end_date
                        : now()->toDateString(),
                ]);
            });

            $property = $tenant->property;
            $stillOccupied = $property?->contracts()->where('status', 'active')->exists();
            if ($property && !$stillOccupied) {
                $property->update(['status' => 'free']);
            }

            $tenant->delete();
        });

        return response()->json(['message' => __('messages.tenants.removed')]);
    }
}
