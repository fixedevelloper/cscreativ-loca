<?php


namespace App\Http\Resources;

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user->only('id', 'name', 'email','phone'),
            'start_date' => $this->start_date,
            'property' => $this->property->only('id', 'name', 'address'),
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'profession' => $this->profession,
            'date_birth' => $this->date_birth,
            'document_type' => $this->document_type,
            'document_id' => $this->document_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

