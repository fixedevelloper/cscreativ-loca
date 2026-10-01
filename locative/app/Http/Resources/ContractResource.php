<?php


namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;



class ContractResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            // L'utilisateur propriétaire du contrat (souvent owner ou agent)
            'user_id' => $this->user_id,

            // 🔹 Informations sur la propriété
            'property' => [
                'id' => $this->property?->id,
                'name' => $this->property?->name,
                'address' => $this->property?->address,
            ],

            // 🔹 Informations sur le locataire
            'tenant_id' => $this->tenant?->id,
            'tenant_name' => $this->tenant?->user?->name,
            'tenant_email' => $this->tenant?->user?->email,
            'tenant_phone' => $this->tenant?->user?->phone,

            // 🔹 Détails du contrat
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'rent_amount' => $this->rent_amount,
            'deposit_amount' => $this->deposit_amount,
            'terms' => $this->terms,
            'status' => $this->status,

            // 🔹 Bail PDF et signatures électroniques
            'lease_document_id' => $this->lease_document_id,
            'signed_document_id' => $this->signed_document_id,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signatures' => $this->whenLoaded('signatures', fn () => $this->signatures->map(fn ($s) => [
                'party' => $s->party,
                'signer' => $s->signer?->name,
                'signed_at' => $s->signed_at?->toIso8601String(),
            ])->values()),

            // 🔹 Date de création formatée
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}

