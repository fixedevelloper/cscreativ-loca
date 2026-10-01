<?php

// Politique de facturation locative : génération des échéances, relances
// impayés et pénalités de retard. Centralisé ici pour ne pas disperser ces
// règles métier dans le code (voir GenerateMonthlyRents, SendRentReminders).

return [

    // Jour du mois où l'échéance est due (ex: 5 => loyer dû le 5 de chaque mois).
    'due_day' => (int) env('RENT_DUE_DAY', 5),

    // Paliers de relance, en jours de retard après la date d'échéance.
    'reminder_days' => [3, 7, 15],

    // Aucune pénalité avant ce nombre de jours de retard.
    'penalty_grace_days' => (int) env('RENT_PENALTY_GRACE_DAYS', 7),

    // Pénalité appliquée une fois le délai de grâce dépassé, en pourcentage
    // du loyer (ex: 0.05 = 5%).
    'penalty_rate' => (float) env('RENT_PENALTY_RATE', 0.05),

];
