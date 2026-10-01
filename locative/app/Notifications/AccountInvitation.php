<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Invitation à définir son mot de passe (locataire ajouté par un bailleur,
 * agent/admin créé par un administrateur). Remplace l'email "Reset Password"
 * de Laravel, en anglais et inadapté à une première connexion. Envoyée dans
 * la langue du destinataire (User::preferredLocale).
 */
class AccountInvitation extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $hours = (int) (config('auth.passwords.invitations.expire') / 60);

        return (new MailMessage)
            ->subject(__('notifications.invitation.subject', ['app' => config('app.name')]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.invitation.intro', ['app' => config('app.name')]))
            ->action(__('notifications.invitation.action'), $this->resetUrl($notifiable))
            ->line(__('notifications.invitation.expiry', ['hours' => $hours]))
            ->line(__('notifications.invitation.ignore'));
    }

    /** Le frontend envoie invitation=1 pour que l'API utilise le bon broker. */
    protected function resetUrl($notifiable): string
    {
        return parent::resetUrl($notifiable) . '&invitation=1';
    }
}
