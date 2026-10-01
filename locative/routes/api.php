<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\MomoCallbackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RentController;
use App\Http\Controllers\RentPaymentController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


// 🔹 Routes publiques
Route::post('/login', [SecurityController::class, 'login'])->middleware('throttle:login');
Route::post('/users', [UserController::class, 'store'])->middleware('throttle:10,1');
Route::get('/plans', [SubscriptionController::class, 'plans']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');

// 🔐 Webhook public MoMo : ici et non dans web.php, où la protection CSRF
// rejetait chaque appel de MoMo (419). Protégé par une re-vérification active
// du statut auprès de MoMo (voir MomoCallbackController), jamais par confiance
// dans le body. MoMo peut notifier en POST ou en PUT selon l'environnement.
Route::match(['post', 'put'], '/momo/callback', [MomoCallbackController::class, 'callback'])
    ->middleware('throttle:60,1')
    ->name('momo.callback');
Route::post('/subscriptions', [SubscriptionController::class, 'store'])->middleware('auth:sanctum');

// 🔹 Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [SecurityController::class, 'logout']);

    // 🔹 Notifications (cloche) — tous les rôles, sans condition d'abonnement
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // 🔹 Profil (tous les rôles, sans condition d'abonnement)
    Route::get('/me', [ProfileController::class, 'show']);
    Route::put('/me', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:5,1');

    // Dashboard accessible seulement si abonnement actif
    Route::middleware('subscription')->get('/dashboard', [DashboardController::class, 'dashboard']);

    // Paiements d'abonnement (le webhook MoMo public est déclaré plus haut,
    // hors auth:sanctum, car MoMo n'a pas de token Sanctum)
    Route::post('/subscription-payments/initiate', [SubscriptionController::class, 'initiatePayment']);
    Route::post('/subscriptions/confirm', [SubscriptionController::class, 'confirm']);

    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::patch('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);

    // 🔹 Routes accessibles seulement si abonnement actif
    Route::middleware('subscription')->group(function () {
        // ->only() : n'exposer que les actions réellement implémentées (une
        // route sans méthode renvoyait une 500, un stub vide un faux 200).
        // Pas de suppression de paiement : un encaissement est une pièce
        // comptable (une annulation devra être une écriture inverse).
        Route::apiResource('inventories', InventoryController::class)->only(['index', 'store', 'show']);
        Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'destroy']);
        Route::apiResource('properties', PropertyController::class)->only(['index', 'store']);
        Route::apiResource('tenants', TenantController::class)->only(['index', 'store', 'destroy']);
        Route::apiResource('rents', RentController::class)->only(['index']);
        Route::apiResource('payments', PaymentController::class)->only(['index', 'store']);
        Route::apiResource('contracts', ContractController::class)->only(['index']);

        Route::get('/rents/{id}/contract', [RentController::class, 'rent_to_contract']);

        // Bail PDF et signature électronique simple
        Route::post('/contracts/{contract}/lease', [LeaseController::class, 'generate']);
        Route::post('/contracts/{contract}/sign', [LeaseController::class, 'sign'])->middleware('throttle:10,1');

        // Demandes d'intervention
        Route::get('/maintenance-requests', [MaintenanceRequestController::class, 'index']);
        Route::post('/maintenance-requests', [MaintenanceRequestController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'show']);
        Route::patch('/maintenance-requests/{maintenanceRequest}/status', [MaintenanceRequestController::class, 'updateStatus']);
        Route::post('/maintenance-requests/{maintenanceRequest}/comments', [MaintenanceRequestController::class, 'comment'])->middleware('throttle:30,1');

        // Paiement du loyer en ligne par le locataire (MoMo)
        Route::post('/rents/{rent}/pay', [RentPaymentController::class, 'initiate'])->middleware('throttle:10,1');
        Route::post('/rent-payments/{reference}/confirm', [RentPaymentController::class, 'confirm'])->middleware('throttle:30,1');
        Route::get('/payments/{id}/contract', [PaymentController::class, 'payment_to_contract']);
        Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
        Route::post('/documents/{id}/share', [DocumentController::class, 'share']);

        // Routes réservées à certains plans
        // TODO: ReportController n'existe pas encore — route désactivée pour éviter un 500.
        // Route::middleware('subscription:pro')->group(function () {
        //     Route::get('/pro-reports', [ReportController::class, 'index']);
        // });
    });

    // 🔹 Routes pour le rôle admin (ignore l’abonnement)
    Route::middleware('role:admin')->group(function () {
        Route::post('/users/staff', [UserController::class, 'storeStaff']); // création agent/admin
        Route::get('/users', [UserController::class, 'index']);
        Route::post('agents/{agentId}/assign-owners', [AgentController::class, 'assignOwners']);
        Route::get('/admin/documents', [DocumentController::class, 'index']); // Exemple admin
        Route::get('/admin/properties', [PropertyController::class, 'index']);
        Route::patch('/users/{user}/suspend', [AdminController::class, 'suspend']);
        Route::patch('/users/{user}/reactivate', [AdminController::class, 'reactivate']);
        Route::get('/admin/subscriptions', [AdminController::class, 'subscriptions']);
        Route::delete('agents/{agentId}/owners/{ownerId}', [AgentController::class, 'detachOwner']);
        // Ajouter d’autres routes admin si besoin
    });

    // 🔹 Routes pour le rôle agent
    // 🔹 Portefeuille d'un agent : l'agent (le sien seulement, voir
    //    AgentController::getOwners) et l'admin (gestion des rattachements).
    Route::middleware('role:agent,admin')->group(function () {
        Route::get('agents/{agentId}/owners', [AgentController::class, 'getOwners']);
    });
});





