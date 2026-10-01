<?php

namespace App\Service\Sms;

use Illuminate\Support\Facades\Log;

/** Pilote par défaut : aucun envoi réel, le SMS est journalisé. */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS (pilote log, non envoyé)', ['to' => $to, 'message' => $message]);
    }
}
