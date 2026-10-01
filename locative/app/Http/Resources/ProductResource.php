<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'memory'        => $this->memory ?? null,           // correspond à la taille/mémoire
            'price'         => $this->price,                   // prix cash
            'price_leasing' => $this->price_leasing ?? null,   // prix leasing
            'image_url'     => $this->image_url ? Storage::url($this->image_url) : null, // chemin complet public
        ];
    }
}

