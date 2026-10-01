<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_name' => $this->contract?->tenant?->user?->name,
            'property_id' => $this->contract?->property_id,
            'property_name' => $this->contract?->property?->name,
            'late_penalty' => $this->late_penalty,
            // paid_amount : withSum(...) des contrôleurs (encaissements réussis).
            'paid_amount' => round((float) ($this->paid_amount ?? 0), 2),
            'remaining' => max(round($this->totalDue() - (float) ($this->paid_amount ?? 0), 2), 0),
            'receipt_document_id' => $this->whenLoaded('receipt', fn () => $this->receipt?->id),
            'month' => $this->month,
            'due_date'=>$this->due_date,
            'amount' => $this->amount,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
