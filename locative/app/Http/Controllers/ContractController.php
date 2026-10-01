<?php


namespace App\Http\Controllers;


use App\Http\Resources\ContractResource;
use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{

    public function index(Request $request)
    {
        $query = Contract::with(['property.owner', 'tenant.user', 'signatures.signer'])->visibleTo(auth()->user());

        // 🔹 Filtre recherche : bien loué (name, city, address) ou nom du locataire
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('property', fn ($p) => $p->where('name', 'like', "%$search%")
                        ->orWhere('city', 'like', "%$search%")
                        ->orWhere('address', 'like', "%$search%"))
                    ->orWhereHas('tenant.user', fn ($u) => $u->where('name', 'like', "%$search%"));
            });
        }

        return $this->paginated($query, $request, ContractResource::class);
    }
}
