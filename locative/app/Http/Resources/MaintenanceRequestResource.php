<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'property' => $this->property?->only('id', 'name', 'address'),
            'contract_id' => $this->contract_id,
            'tenant_name' => $this->contract?->tenant?->user?->name,
            'author' => $this->author?->only('id', 'name', 'role'),
            'comments_count' => $this->comments_count ?? $this->whenLoaded('comments', fn () => $this->comments->count()),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'author' => $c->author?->only('id', 'name', 'role'),
                'created_at' => $c->created_at?->toIso8601String(),
            ])),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
