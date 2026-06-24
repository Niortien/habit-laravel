<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\EntreeController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\SortieController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VarianteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Auth (public) ──────────────────────────────────────────────────────
    Route::post('auth/login',   [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // ── Public catalogue ───────────────────────────────────────────────────
    Route::get('categories',         [ProduitController::class, 'categories']);
    Route::get('produits',           [ProduitController::class, 'index']);
    Route::get('produits/{id}',      [ProduitController::class, 'show']);

    // ── Protected routes ───────────────────────────────────────────────────
    Route::middleware('auth:api')->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Produits (write)
        Route::post('produits',                        [ProduitController::class, 'store']);
        Route::patch('produits/{id}',                  [ProduitController::class, 'update']);
        Route::delete('produits/{id}',                 [ProduitController::class, 'destroy']);
        Route::post('produits/{id}/images',            [ProduitController::class, 'addImage']);
        Route::delete('produits/{id}/images/{imageId}',[ProduitController::class, 'removeImage']);
        Route::get('produits/{id}/mouvements',         [ProduitController::class, 'mouvements']);

        // Variantes
        Route::patch('variantes/{id}',       [VarianteController::class, 'update']);
        Route::delete('variantes/{id}',      [VarianteController::class, 'destroy']);
        Route::patch('variantes/{id}/stock', [VarianteController::class, 'adjustStock']);

        // Stock
        Route::get('stock',           [StockController::class, 'index']);
        Route::get('stock/alertes',   [StockController::class, 'alertes']);
        Route::get('stock/mouvements',[StockController::class, 'mouvements']);

        // Entrées
        Route::get('entrees',              [EntreeController::class, 'index']);
        Route::get('entrees/{id}',         [EntreeController::class, 'show']);
        Route::post('entrees',             [EntreeController::class, 'store']);
        Route::patch('entrees/{id}',       [EntreeController::class, 'update']);
        Route::delete('entrees/{id}',      [EntreeController::class, 'destroy']);
        Route::patch('entrees/{id}/annuler',[EntreeController::class, 'annuler']);

        // Sorties
        Route::get('sorties',              [SortieController::class, 'index']);
        Route::get('sorties/{id}',         [SortieController::class, 'show']);
        Route::post('sorties',             [SortieController::class, 'store']);
        Route::patch('sorties/{id}',       [SortieController::class, 'update']);
        Route::delete('sorties/{id}',      [SortieController::class, 'destroy']);
        Route::patch('sorties/{id}/annuler',[SortieController::class, 'annuler']);

        // Caisse
        Route::get('caisse/sessions',                          [CaisseController::class, 'listSessions']);
        Route::get('caisse/sessions/active',                   [CaisseController::class, 'activeSession']);
        Route::post('caisse/sessions',                         [CaisseController::class, 'openSession']);
        Route::patch('caisse/sessions/{id}/fermer',            [CaisseController::class, 'closeSession']);
        Route::get('caisse/sessions/{id}/transactions',        [CaisseController::class, 'listTransactions']);
        Route::post('caisse/transactions',                     [CaisseController::class, 'createTransaction']);
        Route::get('caisse/resume-jour',                       [CaisseController::class, 'resumeJour']);

        // Rapports
        Route::get('rapports/resume-dashboard', [RapportController::class, 'resumeDashboard']);
        Route::get('rapports/ventes',           [RapportController::class, 'ventes']);
        Route::get('rapports/stock-valeur',     [RapportController::class, 'stockValeur']);
        Route::get('rapports/top-produits',     [RapportController::class, 'topProduits']);
        Route::get('rapports/flux-tresorerie',  [RapportController::class, 'fluxTresorerie']);
        Route::get('rapports/export-excel',     [RapportController::class, 'exportExcel']);
        Route::get('rapports/export-pdf',       [RapportController::class, 'exportPdf']);

        // ── Admin-only ──────────────────────────────────────────────────────
        Route::middleware('role:ADMIN')->group(function () {

            // Users
            Route::get('users',         [UserController::class, 'index']);
            Route::post('users',        [UserController::class, 'store']);
            Route::get('users/{id}',    [UserController::class, 'show']);
            Route::patch('users/{id}',  [UserController::class, 'update']);
            Route::delete('users/{id}', [UserController::class, 'destroy']);

            // Boutiques
            Route::get('boutiques',         [BoutiqueController::class, 'index']);
            Route::post('boutiques',        [BoutiqueController::class, 'store']);
            Route::get('boutiques/{id}',    [BoutiqueController::class, 'show']);
            Route::patch('boutiques/{id}',  [BoutiqueController::class, 'update']);
            Route::delete('boutiques/{id}', [BoutiqueController::class, 'destroy']);
        });
    });
});
