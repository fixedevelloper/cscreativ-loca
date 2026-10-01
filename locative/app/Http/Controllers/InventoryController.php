<?php


namespace App\Http\Controllers;


use App\Http\Requests\StoreInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{

    public function index(Request $request)
    {
        $query = Inventory::with(['property', 'contract.tenant.user', 'items'])
            ->visibleTo(auth()->user());

        if ($search = $request->input('search')) {
            $query->whereHas('contract.tenant.user', fn($q) =>
            $q->where('name', 'like', "%$search%")
            );
        }

        // Même format de liste que le reste de l'API (avant : format
        // paginator Laravel {data, links, meta}, d'où un total toujours vide
        // côté frontend).
        return $this->paginated($query->latest(), $request, InventoryResource::class);
    }


    public function store(StoreInventoryRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $inventory = Inventory::create([
                'property_id' => $validated['propertyId'],
                'contract_id' => $validated['contract_id'] ?? null,
                'user_id' => auth()->id(),
                'type' => $validated['type'],
                'general_condition' => $validated['general_condition'] ?? null,
                'inventory_date' => now(),
            ]);

            foreach ($validated['items'] as $item) {
                $inventory->items()->create($item);
            }

            DB::commit();
            return response()->json([
                'message' => __('messages.inventories.created'),
                'inventory' => $inventory->load('items'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            // Jamais le message brut d'exception (SQL…) au client : rendu
            // générique par le gestionnaire d'exceptions commun.
            throw $e;
        }
    }

    public function show($id)
    {
        $inventory = Inventory::with(['property', 'contract', 'items', 'user'])->findOrFail($id);
        $this->authorize('view', $inventory);
        return response()->json($inventory);
    }

    public function compareInventories($propertyId)
    {
        $property = Property::findOrFail($propertyId);
        $this->authorize('view', $property);

        $entry = Inventory::where('property_id', $propertyId)->where('type', 'entry')->latest()->first();
        $exit = Inventory::where('property_id', $propertyId)->where('type', 'exit')->latest()->first();

        if (!$entry || !$exit) {
            return $this->error(__('messages.inventories.both_required'), 400);
        }

        $differences = $exit->items->map(function ($exitItem) use ($entry) {
            $entryItem = $entry->items->firstWhere('item_name', $exitItem->item_name);
            if (!$entryItem) {
                return ['item' => $exitItem->item_name, 'status' => 'Nouveau ou non listé à l’entrée'];
            }
            if ($exitItem->condition !== $entryItem->condition) {
                return ['item' => $exitItem->item_name, 'status' => "Changement d’état : {$entryItem->condition} → {$exitItem->condition}"];
            }
            return null;
        })->filter();

        return response()->json([
            'property' => $entry->property->name,
            'differences' => array_values($differences->toArray())
        ]);
    }

}

