<?php

namespace App\Notifications\Channels;

use App\Service\Sms\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Canal "sms" : appelle $notification->toSms($notifiable) et envoie au
 * numéro du destinataire (indicatif MOMO_COUNTRY_CODE ajouté aux numéros
 * locaux à 9 chiffres).
 */
class SmsChannel
{
    public function __construct(private readonly SmsSender $sender)
    {
    }

    public function send($notifiable, Notification $notification): void
    {
        $phone = preg_replace('/\D/', '', (string) $notifiable->phone);
        if ($phone === '' || !method_exists($notification, 'toSms')) {
            return;
        }

        if (strlen($phone) === 9) {
            $phone = config('services.momo.country_code', '237') . $phone;
        }

        $this->sender->send($phone, $notification->toSms($notifiable));
    }
}
