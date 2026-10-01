<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Models\User;

/**
 * Événement sur une demande d'intervention : création, changement de
 * statut ou nouveau commentaire. Envoyé aux participants sauf à l'auteur.
 */
class MaintenanceUpdate extends InAppNotification
{
    /** @param string $event created | status | comment */
    public function __construct(
        private readonly MaintenanceRequest $request,
        private readonly string $event,
        private readonly User $actor,
    ) {
    }

    protected function type(): string
    {
        return "maintenance_{$this->event}";
    }

    protected function params(): array
    {
        return [
            'title' => $this->request->title,
            'property' => $this->request->property?->name,
            'status' => $this->request->status,
            'priority' => $this->request->priority,
            'actor' => $this->actor->name,
        ];
    }

    protected function link(): ?string
    {
        return "/maintenance?id={$this->request->id}";
    }
}
