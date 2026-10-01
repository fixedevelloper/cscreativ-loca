<?php

// 🔐 Catalogue des plans d'abonnement, source de vérité côté serveur.
// Le client ne fournit jamais le prix ni les limites : uniquement le nom
// du plan choisi, qui est résolu ici. Voir SubscriptionController::store.

return [
    'free' => [
        'label' => 'Free',
        'price' => 0,
        'property_limit' => 1,
        'tenant_limit' => 1,
        'duration_days' => 3650,
    ],
    'starter' => [
        'label' => 'Starter',
        'price' => 5000,
        'property_limit' => 5,
        'tenant_limit' => 10,
        'duration_days' => 30,
    ],
    'pro' => [
        'label' => 'Pro',
        'price' => 15000,
        'property_limit' => 25,
        'tenant_limit' => 50,
        'duration_days' => 30,
    ],
    'agency' => [
        'label' => 'Agency',
        'price' => 40000,
        'property_limit' => null, // illimité
        'tenant_limit' => null,
        'duration_days' => 30,
    ],
];
