<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'inventory_date' => $this->inventory_date,
            'general_condition' => $this->general_condition,
            'contract_id' => $this->contract_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),

            // 🔗 Relations
            'tenant_name' => optional($this->contract?->tenant?->user)->name,
            'tenant_email' => optional($this->contract?->tenant?->user)->email,
            // Le bien est porté par l'état des lieux lui-même (le bail est
            // facultatif) : avant, nom vide pour un état des lieux sans bail.
            'property_name' => $this->property?->name,
            'property_address' => $this->property?->address,

            // 🧾 Liste des items
            'items' => InventoryItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

