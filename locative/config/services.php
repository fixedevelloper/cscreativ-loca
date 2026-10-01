<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    // Envoi de SMS (relances) : "log" = journalisé, pas d'envoi réel.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'momo' => [
        'env' => env('MOMO_ENV', 'sandbox'),
        'base_url' => env('MOMO_BASE_URL'),
        'subscription_key' => env('MOMO_SUBSCRIPTION_KEY'),
        'api_user' => env('MOMO_API_USER'),
        'api_key' => env('MOMO_API_KEY'),
        'currency' => env('MOMO_CURRENCY'),
        // Indicatif ajouté au numéro local saisi par le payeur (237 = Cameroun).
        'country_code' => env('MOMO_COUNTRY_CODE', '237'),
        // URL publique HTTPS du webhook (doit correspondre au domaine déclaré
        // chez MTN). À défaut : route('momo.callback'), basée sur APP_URL.
        'callback_url' => env('MOMO_CALLBACK_URL'),
    ],

];
