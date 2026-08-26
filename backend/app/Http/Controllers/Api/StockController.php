<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Écran 3 « Stock et consommation » (§2).
 *
 * Renvoie l'écran entier en une requête : le bandeau de stock, les totaux du
 * mois et les deux lectures de la consommation par véhicule. Le gestionnaire
 * compare le mois en cours à l'historique complet, découper en trois appels
 * ne ferait que multiplier les allers-retours.
 */
class StockController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function index(Request $request): JsonResponse
    {
        $aujourdhui = Carbon::now();
        $annee = $request->integer('annee', $aujourdhui->year);
        $mois = $request->integer('mois', $aujourdhui->month);

        return response()->json([
            'data' => [
                'synthese' => $this->stock->synthese(),
                'totaux_mois' => $this->stock->totauxMois($annee, $mois),
                'consommation_par_vehicule' => $this->stock->consommationParVehicule($annee, $mois),
                'consommation_cumulee' => $this->stock->consommationParVehicule(),
            ],
        ]);
    }
}
