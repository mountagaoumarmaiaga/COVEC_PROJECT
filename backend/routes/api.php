<?php

declare(strict_types=1);

use App\Enums\ModeSuivi;
use App\Enums\Role;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarburantController;
use App\Http\Controllers\Api\ChauffeurController;
use App\Http\Controllers\Api\EntreeController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\SortieController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\UtilisateurController;
use App\Http\Controllers\Api\VehiculeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API — Suivi du carburant COVEC
|--------------------------------------------------------------------------
|
| Les routes sont écrites une par une plutôt qu'en apiResource : ce fichier
| est la matrice des permissions, et on doit pouvoir la lire d'un coup d'œil
| sans ouvrir dix contrôleurs.
|
| Trois rôles :
|   — pompiste      : sert les pleins, consulte
|   — gestionnaire  : tout, y compris corrections, référentiel et comptes
|   — consultation  : lecture seule et export
|
*/

// Freinée : les matricules sont courts et devinables, et sans limite un mot
// de passe de huit caractères tombe en quelques heures d'essais automatisés.
Route::post('connexion', [AuthController::class, 'connexion'])
    ->middleware('throttle:connexion')
    ->name('connexion');

Route::middleware('auth:sanctum')->group(function () {
    // ---------------------------------------------------------------
    // Son propre compte
    // ---------------------------------------------------------------
    Route::post('deconnexion', [AuthController::class, 'deconnexion'])->name('deconnexion');
    Route::get('moi', [AuthController::class, 'moi'])->name('moi');
    Route::put('moi/mot-de-passe', [AuthController::class, 'changerMotDePasse'])
        ->middleware('throttle:mot-de-passe')
        ->name('moi.mot-de-passe');

    // ---------------------------------------------------------------
    // Lecture — accessible aux trois rôles
    // ---------------------------------------------------------------
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('exports/mensuel', [ExportController::class, 'mensuel'])->name('exports.mensuel');

    Route::get('vehicules', [VehiculeController::class, 'index'])->name('vehicules.index');
    Route::get('vehicules/{vehicule}', [VehiculeController::class, 'show'])->name('vehicules.show');
    Route::get('vehicules/{vehicule}/historique', [VehiculeController::class, 'historique'])
        ->name('vehicules.historique');

    Route::get('chauffeurs', [ChauffeurController::class, 'index'])->name('chauffeurs.index');
    Route::get('carburants', [CarburantController::class, 'index'])->name('carburants.index');
    Route::get('carburants/{carburant}', [CarburantController::class, 'show'])->name('carburants.show');

    Route::get('entrees', [EntreeController::class, 'index'])->name('entrees.index');
    Route::get('sorties', [SortieController::class, 'index'])->name('sorties.index');
    Route::get('sorties/{sortie}', [SortieController::class, 'show'])->name('sorties.show');

    Route::get('referentiel/modes-suivi', fn () => response()->json(['data' => ModeSuivi::options()]))
        ->name('referentiel.modes-suivi');
    Route::get('referentiel/roles', fn () => response()->json(['data' => Role::options()]))
        ->name('referentiel.roles');

    // ---------------------------------------------------------------
    // Servir un plein — pompiste et gestionnaire
    //
    // Le pompiste enregistre, il ne corrige pas : modifier ou supprimer un
    // plein reviendrait à contourner les contrôles du §5 en retouchant le
    // plein précédent.
    // ---------------------------------------------------------------
    Route::post('sorties', [SortieController::class, 'store'])
        ->middleware('role:pompiste,gestionnaire')
        ->name('sorties.store');

    // ---------------------------------------------------------------
    // Gestion — gestionnaire seul
    // ---------------------------------------------------------------
    Route::middleware('role:gestionnaire')->group(function () {
        Route::match(['put', 'patch'], 'sorties/{sortie}', [SortieController::class, 'update'])
            ->name('sorties.update');
        Route::delete('sorties/{sortie}', [SortieController::class, 'destroy'])
            ->name('sorties.destroy');

        Route::post('entrees', [EntreeController::class, 'store'])->name('entrees.store');
        Route::get('entrees/{entree}', [EntreeController::class, 'show'])->name('entrees.show');
        Route::match(['put', 'patch'], 'entrees/{entree}', [EntreeController::class, 'update'])
            ->name('entrees.update');
        Route::delete('entrees/{entree}', [EntreeController::class, 'destroy'])
            ->name('entrees.destroy');

        Route::post('vehicules', [VehiculeController::class, 'store'])->name('vehicules.store');
        Route::match(['put', 'patch'], 'vehicules/{vehicule}', [VehiculeController::class, 'update'])
            ->name('vehicules.update');
        Route::delete('vehicules/{vehicule}', [VehiculeController::class, 'destroy'])
            ->name('vehicules.destroy');

        Route::post('chauffeurs', [ChauffeurController::class, 'store'])->name('chauffeurs.store');
        Route::get('chauffeurs/{chauffeur}', [ChauffeurController::class, 'show'])->name('chauffeurs.show');
        Route::match(['put', 'patch'], 'chauffeurs/{chauffeur}', [ChauffeurController::class, 'update'])
            ->name('chauffeurs.update');
        Route::delete('chauffeurs/{chauffeur}', [ChauffeurController::class, 'destroy'])
            ->name('chauffeurs.destroy');

        // Ni création ni suppression de carburant : la station en distribue
        // deux, posés à l'installation.
        Route::put('carburants/{carburant}', [CarburantController::class, 'update'])
            ->name('carburants.update');

        Route::get('utilisateurs', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
        Route::post('utilisateurs', [UtilisateurController::class, 'store'])->name('utilisateurs.store');
        Route::get('utilisateurs/{utilisateur}', [UtilisateurController::class, 'show'])
            ->name('utilisateurs.show');
        Route::match(['put', 'patch'], 'utilisateurs/{utilisateur}', [UtilisateurController::class, 'update'])
            ->name('utilisateurs.update');
        Route::delete('utilisateurs/{utilisateur}', [UtilisateurController::class, 'destroy'])
            ->name('utilisateurs.destroy');
    });
});
