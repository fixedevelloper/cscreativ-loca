<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notification affichée dans l'application (cloche). Stockée comme
 * { type, params, link } et traduite côté interface (clé
 * notifications.<type> du frontend) : elle s'affiche dans la langue
 * courante de l'utilisateur, même s'il en change après coup.
 */
abstract class InAppNotification extends Notification
{
    /** Type = clé de traduction côté interface (ex. "rent_paid"). */
    abstract protected function type(): string;

    /** Paramètres interpolés dans le message traduit. */
    abstract protected function params(): array;

    /** Page à ouvrir au clic (chemin de l'interface). */
    abstract protected function link(): ?string;

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return ['type' => $this->type(), 'params' => $this->params(), 'link' => $this->link()];
    }
}
