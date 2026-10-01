<?php


namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'rent_id' => $this->rent_id,
            'rent_month' => optional($this->rent)->month,
            'tenant_name' => $this->rent?->contract?->tenant?->user?->name,
            'property_name' => $this->rent?->contract?->property?->name,
            'owner_name' => $this->rent?->contract?->property?->owner?->name,
            'user_id' => $this->user_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}


