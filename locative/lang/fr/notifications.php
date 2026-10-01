<?php

return [
    'greeting' => 'Bonjour :name,',
    'signature' => 'Pour toute question, contactez votre gestionnaire.',

    'invitation' => [
        'subject' => 'Bienvenue sur :app : créez votre mot de passe',
        'intro' => 'Un compte a été créé pour vous sur :app. Pour y accéder, choisissez votre mot de passe.',
        'action' => 'Créer mon mot de passe',
        'expiry' => 'Ce lien est valable :hours heures.',
        'ignore' => 'Si vous n\'attendiez pas ce message, vous pouvez l\'ignorer.',
    ],

    'rent_overdue' => [
        'subject' => [
            'j3' => 'Rappel : loyer arrivé à échéance (:property)',
            'j7' => 'Rappel : loyer toujours impayé (:property)',
            'j15' => 'Mise en demeure : loyer impayé (:property)',
        ],
        'j3' => 'Nous vous rappelons que le loyer du bien « :property » pour la période :month est arrivé à échéance et reste impayé à ce jour.',
        'j7' => 'Le loyer du bien « :property » pour la période :month n\'a toujours pas été réglé (:days jours de retard).',
        'j15' => 'Malgré nos précédentes relances, le loyer du bien « :property » pour la période :month reste impayé, avec :days jours de retard.',
        'amount' => 'Montant dû : :amount.',
        'amount_with_penalty' => 'Montant dû (loyer + pénalité de retard) : :amount.',
        'please_pay' => 'Merci de procéder au règlement dans les plus brefs délais.',
        'please_settle' => 'Merci de régulariser votre situation dans les meilleurs délais.',
        'formal_notice' => 'À défaut de régularisation sous 8 jours, une procédure de recouvrement pourra être engagée.',
        'sms' => ':app : loyer :month impayé pour « :property ». Montant dû : :amount FCFA. Merci de régulariser rapidement.',
    ],
];
