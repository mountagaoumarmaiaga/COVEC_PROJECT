<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarburantRequest;
use App\Http\Resources\CarburantResource;
use App\Models\Carburant;
use App\Models\Cuve;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Référentiel « Carburants et cuves » (§3).
 *
 * Ni création ni suppression : la station distribue du gasoil et de l'essence,
 * ce sont deux lignes posées à l'installation. Supprimer un carburant
 * emporterait sa cuve, ses livraisons et le rattachement de ses véhicules.
 */
class CarburantController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CarburantResource::collection(
            Carburant::query()->with('cuve')->orderBy('libelle')->get(),
        );
    }

    public function show(Carburant $carburant): CarburantResource
    {
        Cuve::pour($carburant);

        return CarburantResource::make($carburant->load('cuve'));
    }

    public function update(CarburantRequest $request, Carburant $carburant): CarburantResource
    {
        $valide = $request->validated();

        DB::transaction(function () use ($carburant, $valide) {
            $carburant->update([
                'libelle' => $valide['libelle'],
                'prix_par_defaut' => $valide['prix_par_defaut'],
                'actif' => $valide['actif'] ?? $carburant->actif,
            ]);

            Cuve::pour($carburant)->update([
                'nom' => $valide['cuve']['nom'],
                'capacite' => $valide['cuve']['capacite'],
            ]);
        });

        return CarburantResource::make($carburant->fresh('cuve'));
    }
}
