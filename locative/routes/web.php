<?php

use App\Http\Controllers\FrontController;
use Illuminate\Support\Facades\Route;


// Le webhook MoMo est déclaré dans routes/api.php (pas de CSRF côté API).

// ⚠️ Pages de paiement simulé — UNIQUEMENT pour le développement local.
// confirmPayment() active un abonnement sans vérification : ne jamais
// exposer ces routes en dehors de local/testing (faille "abonnement gratuit").
if (app()->environment(['local', 'testing'])) {
    Route::get('/payment/mock/{ref}', [FrontController::class, 'mockPayment'])->name('payment.mock');
    Route::post('/payment/confirm/{ref}', [FrontController::class, 'confirmPayment'])->name('payment.confirm');
}
