<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'surface' => $this->surface,
            'number_piece' => $this->number_piece,
            'monthly_charge' => $this->monthly_charge,
            'type' => $this->type,
            'monthly_rent' => $this->monthly_rent,
            'status' => $this->status,
            'owner' => $this->owner?->only('id', 'name', 'email'),
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}


