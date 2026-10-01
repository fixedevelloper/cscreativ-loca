<?php

namespace App\Service\Sms;

/**
 * Envoi de SMS. Pilote choisi par config('services.sms.driver') :
 * "log" (par défaut : journalise sans envoyer) tant qu'aucun fournisseur
 * n'est configuré. Brancher un fournisseur = une classe implémentant cette
 * interface + son enregistrement dans AppServiceProvider.
 */
interface SmsSender
{
    /** @param string $to numéro au format international (ex. 237677000000) */
    public function send(string $to, string $message): void;
}
