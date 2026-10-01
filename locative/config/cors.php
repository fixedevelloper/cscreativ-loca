<?php

// 🔐 CORS explicite : aucun disque par défaut, aucune origine générique.
// FRONTEND_URLS accepte une liste séparée par des virgules (dev + prod).

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'payment/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('FRONTEND_URLS', 'http://localhost:3000'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer token via Authorization header, pas de cookie Sanctum SPA ici :
    // pas besoin d'autoriser les credentials cross-origin.
    'supports_credentials' => false,

];
