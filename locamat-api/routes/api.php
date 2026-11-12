<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MaterielController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReclamationController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentification et compte
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/super-admin/login', [AuthController::class, 'loginSuperAdmin'])->middleware('throttle:10,1');
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('verification.verify');
Route::post('/email/verification-notification', [AuthController::class, 'resendEmailVerification'])->middleware('throttle:5,1');
Route::post('/account-lock/support', [AuthController::class, 'sendLockedAccountSupportRequest'])->middleware('throttle:5,1');
Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:5,1');
Route::post('/verify-reset-code', [AuthController::class, 'verifyPasswordResetCode'])->middleware('throttle:10,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/profile/become-owner', [AuthController::class, 'becomeOwner']);

    Route::get('/user', function (Request $request) {
        return UserResource::make($request->user()->load('roles'))->response();
    });

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
});

/*
|--------------------------------------------------------------------------
| Catalogue public
|--------------------------------------------------------------------------
*/
Route::get('/categories', [CatalogueController::class, 'categories']);
Route::get('/materiels', [CatalogueController::class, 'index']);
Route::get('/materiels/{materiel}/periodes-reservees', [CatalogueController::class, 'periodesReservees']);
Route::get('/materiels/{materiel}', [CatalogueController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Administration (administrateur et super-administrateur)
|--------------------------------------------------------------------------
| Un administrateur gère les comptes, les matériels et les catégories. Il ne peut jamais
| changer le rôle d'un compte : aucune route ne l'expose.
*/
Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);

    Route::get('/categories', [AdminController::class, 'categories']);
    Route::post('/categories', [AdminController::class, 'storeCategory']);
    Route::put('/categories/{category}', [AdminController::class, 'updateCategory']);
    Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory']);

    Route::get('/utilisateurs', [AdminController::class, 'utilisateurs']);
    Route::patch('/utilisateurs/{user}/statut', [AdminController::class, 'updateStatus']);
    Route::patch('/utilisateurs/{user}/deverrouiller', [AdminController::class, 'unlockAccount']);
    Route::patch('/utilisateurs/{user}/profil', [AdminController::class, 'updateProfile']);
    Route::delete('/utilisateurs/{user}', [AdminController::class, 'destroyUser']);

    Route::get('/materiels', [AdminController::class, 'materiels']);
    Route::patch('/materiels/{materiel}/disponibilite', [AdminController::class, 'updateAvailability']);
    Route::delete('/materiels/{materiel}', [AdminController::class, 'destroyMateriel']);

    Route::get('/locations', [AdminController::class, 'locations']);
    Route::get('/reclamations', [ReclamationController::class, 'indexForAdmin']);
    Route::patch('/reclamations/{reclamation}', [ReclamationController::class, 'update'])
        ->whereNumber('reclamation');
});

/*
|--------------------------------------------------------------------------
| Super-administration : historique de la plateforme et restauration
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('super-admin')->group(function () {
    Route::get('/apercu', [SuperAdminController::class, 'apercu']);
    Route::get('/journal', [SuperAdminController::class, 'journal']);
    Route::get('/utilisateurs', [SuperAdminController::class, 'utilisateurs']);

    Route::get('/administrateurs', [SuperAdminController::class, 'administrateurs']);
    Route::post('/administrateurs', [SuperAdminController::class, 'storeAdministrateur']);

    Route::get('/corbeille', [SuperAdminController::class, 'corbeille']);
    Route::patch('/corbeille/{type}/{id}/restaurer', [SuperAdminController::class, 'restaurer'])
        ->whereIn('type', ['utilisateurs', 'materiels', 'categories'])
        ->whereNumber('id');
    Route::delete('/corbeille/{type}/{id}', [SuperAdminController::class, 'purger'])
        ->whereIn('type', ['utilisateurs', 'materiels', 'categories'])
        ->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Propriétaire
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:proprietaire'])->group(function () {
    Route::get('/proprietaire/materiels', [MaterielController::class, 'index']);
    Route::post('/materiels', [MaterielController::class, 'store']);
    Route::put('/materiels/{materiel}', [MaterielController::class, 'update']);
    Route::delete('/materiels/{materiel}', [MaterielController::class, 'destroy']);
    Route::patch('/materiels/{materiel}/disponibilite', [MaterielController::class, 'updateAvailability']);

    Route::get('/proprietaire/demandes', [LocationController::class, 'demandes']);
    Route::get('/proprietaire/stats', [LocationController::class, 'stats']);
    Route::get('/proprietaire/locations', [LocationController::class, 'indexForProprietaire']);

    Route::patch('/proprietaire/locations/{location}/confirmer', [LocationController::class, 'confirmer']);
    Route::patch('/proprietaire/locations/{location}/refuser', [LocationController::class, 'refuser']);
    Route::patch('/proprietaire/locations/{location}/terminer', [LocationController::class, 'terminer']);
    Route::patch('/proprietaire/locations/{location}/prolongation/confirmer', [LocationController::class, 'confirmerProlongation']);
    Route::patch('/proprietaire/locations/{location}/prolongation/refuser', [LocationController::class, 'refuserProlongation']);

    // Anciens chemins, conservés pour la compatibilité.
    Route::patch('/locations/{location}/confirmer', [LocationController::class, 'confirmer']);
    Route::patch('/locations/{location}/refuser', [LocationController::class, 'refuser']);
    Route::patch('/locations/{location}/terminer', [LocationController::class, 'terminer']);
});

/*
|--------------------------------------------------------------------------
| Locataire
|--------------------------------------------------------------------------
*/
// Un propriétaire peut aussi louer le matériel des autres (il ne peut pas louer le sien).
Route::middleware(['auth:sanctum', 'role:locataire,proprietaire'])->group(function () {
    Route::get('/locataire/locations', [LocationController::class, 'indexForLocataire']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::patch('/locations/{location}/annuler', [LocationController::class, 'annuler']);
    Route::post('/locations/{location}/prolongation', [LocationController::class, 'demanderProlongation']);
    Route::get('/locataire/reclamations', [ReclamationController::class, 'indexForLocataire']);
    Route::post('/locataire/reclamations', [ReclamationController::class, 'store'])->middleware('throttle:5,1');
});
