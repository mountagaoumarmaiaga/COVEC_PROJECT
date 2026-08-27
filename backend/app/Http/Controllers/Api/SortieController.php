<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SortieRequest;
use App\Http\Resources\SortieResource;
use App\Models\Sortie;
use App\Models\Vehicule;
use App\Services\ConsommationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Écran 2 « Sorties » (§2) : un véhicule se sert à la cuve.
 *
 * Chaque écriture relance le recalcul de toute la chaîne du véhicule plutôt
 * que du seul plein touché. Une saisie antidatée s'insère au milieu de
 * l'historique et décale la distance de tous les pleins suivants ; ne
 * recalculer que le dernier laisserait des consommations fausses derrière soi.
 */
class SortieController extends Controller
{
    public function __construct(private readonly ConsommationService $consommation) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $sorties = Sortie::query()
            ->with(['vehicule.carburant', 'chauffeur'])
            ->when(
                $request->filled('vehicule_id'),
                fn ($q) => $q->where('vehicule_id', $request->integer('vehicule_id')),
            )
            ->when(
                $request->filled('chauffeur_id'),
                fn ($q) => $q->where('chauffeur_id', $request->integer('chauffeur_id')),
            )
            ->when(
                $request->filled('annee') && $request->filled('mois'),
                fn ($q) => $q->duMois($request->integer('annee'), $request->integer('mois')),
            )
            ->when(
                $request->boolean('anomalies_seulement'),
                fn ($q) => $q->where('anomalie', true),
            )
            ->recherche($request->string('recherche'))
            ->orderByDesc('date_sortie')
            ->orderByDesc('id')
            ->paginate($this->parPage($request))
            ->withQueryString();

        return SortieResource::collection($sorties);
    }

    public function store(SortieRequest $request): JsonResponse
    {
        $sortie = DB::transaction(function () use ($request) {
            $sortie = Sortie::create($request->validated());
            $this->consommation->recalculerChaine($sortie->vehicule);

            return $sortie;
        });

        return SortieResource::make($sortie->fresh(['vehicule.carburant', 'chauffeur']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Sortie $sortie): SortieResource
    {
        return SortieResource::make($sortie->load(['vehicule.carburant', 'chauffeur']));
    }

    public function update(SortieRequest $request, Sortie $sortie): SortieResource
    {
        DB::transaction(function () use ($request, $sortie) {
            $ancienVehiculeId = $sortie->vehicule_id;

            $sortie->update($request->validated());
            $this->consommation->recalculerChaine($sortie->vehicule()->first());

            // Déplacer un plein d'un véhicule à l'autre laisse un trou dans la
            // chaîne du véhicule d'origine, qu'il faut refermer.
            if ($ancienVehiculeId !== $sortie->vehicule_id) {
                $ancien = Vehicule::query()->find($ancienVehiculeId);

                if ($ancien !== null) {
                    $this->consommation->recalculerChaine($ancien);
                }
            }
        });

        return SortieResource::make($sortie->fresh(['vehicule.carburant', 'chauffeur']));
    }

    public function destroy(Sortie $sortie): JsonResponse
    {
        DB::transaction(function () use ($sortie) {
            $vehicule = $sortie->vehicule()->first();
            $sortie->delete();

            if ($vehicule !== null) {
                $this->consommation->recalculerChaine($vehicule);
            }
        });

        return response()->json(status: 204);
    }
}
