<?php


namespace App\Http\Controllers;


use App\Http\Requests\StorePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::with('owner')->visibleTo(auth()->user());

        // 🔹 Filtre recherche (name, city, address)
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('city', 'like', "%$search%")
                    ->orWhere('address', 'like', "%$search%");
            });
        }

        return $this->paginated($query, $request, PropertyResource::class);
    }


    public function store(StorePropertyRequest $request)
    {
        $user = auth()->user();
        $owner = User::findOrFail($request->ownerId());

        // L'abonnement et la limite de biens sont ceux du BAILLEUR (un agent
        // n'a pas d'abonnement propre). L'admin en est exempté.
        if ($user->role !== 'admin') {
            $subscription = $owner->activeSubscription();

            $forSelf = $owner->id === $user->id;

            if (!$subscription) {
                return $this->error($forSelf ? __('messages.properties.no_subscription_self') : __('messages.properties.no_subscription_owner'), 403);
            }

            if ($subscription->limitReached('property', Property::where('user_id', $owner->id)->count())) {
                return $this->error($forSelf
                    ? __('messages.properties.limit_self')
                    : __('messages.properties.limit_owner'), 403);
            }
        }

        $data = collect($request->validated())->except('owner_id')->all();
        $data['user_id'] = $owner->id;
        $property = Property::create($data);

        return response()->json(['data' => new PropertyResource($property->load('owner'))]);
    }
}

