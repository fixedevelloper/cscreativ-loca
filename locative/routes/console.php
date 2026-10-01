<?php

use Illuminate\Support\Facades\Schedule;

// Filet de sécurité en complément du webhook MoMo (voir MomoCallbackController) :
// re-vérifie activement les paiements restés "pending" (webhook manqué/en retard).
Schedule::command('app:get-status-momo')->everyFiveMinutes()->withoutOverlapping();

// Clôt les baux arrivés à échéance (avant la génération des loyers).
Schedule::command('app:expire-contracts')->dailyAt('01:00')->withoutOverlapping();

// Génère l'échéance de loyer du mois pour chaque contrat actif.
Schedule::command('app:generate-rent')->monthlyOn(1, '02:00')->withoutOverlapping();

// Détecte les loyers impayés et envoie les relances J+3/J+7/J+15.
Schedule::command('app:send-rent-reminders')->dailyAt('08:00')->withoutOverlapping();

// Purge des tokens API expirés (voir config/sanctum.php 'expiration').
Schedule::command('sanctum:prune-expired --hours=24')->daily();
