<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogueController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MaterielController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return UserResource::make($request->user())->response();
})->middleware('auth:sanctum');

Route::get('/materiels', [CatalogueController::class, 'index']);
Route::get('/materiels/{materiel}/periodes-reservees', [CatalogueController::class, 'periodesReservees']);
Route::get('/materiels/{materiel}', [CatalogueController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/utilisateurs', [AdminController::class, 'utilisateurs']);
    Route::patch('/utilisateurs/{user}/role', [AdminController::class, 'updateRole']);
    Route::patch('/utilisateurs/{user}/statut', [AdminController::class, 'updateStatus']);
    Route::get('/materiels', [AdminController::class, 'materiels']);
    Route::patch('/materiels/{materiel}/disponibilite', [AdminController::class, 'updateAvailability']);
    Route::get('/locations', [AdminController::class, 'locations']);
});

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
    Route::patch('/locations/{location}/confirmer', [LocationController::class, 'confirmer']);
    Route::patch('/locations/{location}/refuser', [LocationController::class, 'refuser']);
    Route::patch('/locations/{location}/terminer', [LocationController::class, 'terminer']);
});

Route::middleware(['auth:sanctum', 'role:locataire'])->group(function () {
    Route::get('/locataire/locations', [LocationController::class, 'indexForLocataire']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::patch('/locations/{location}/annuler', [LocationController::class, 'annuler']);
});
