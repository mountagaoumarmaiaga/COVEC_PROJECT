<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehiculeRequest;
use App\Http\Resources\SortieResource;
use App\Http\Resources\VehiculeResource;
use App\Models\Vehicule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Référentiel « Véhicules et engins » (§3). */
class VehiculeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vehicules = Vehicule::query()
            ->with('carburant')
            ->when($request->boolean('actifs_seulement'), fn ($q) => $q->actifs())
            ->recherche($request->string('recherche'))
            ->orderBy('code');

        return VehiculeResource::collection(
            $request->boolean('tous')
                ? $vehicules->get()
                : $vehicules->paginate($this->parPage($request))->withQueryString(),
        );
    }

    public function store(VehiculeRequest $request): JsonResponse
    {
        $vehicule = Vehicule::create($request->validated());
        $vehicule->load('carburant');

        return VehiculeResource::make($vehicule)->response()->setStatusCode(201);
    }

    public function show(Vehicule $vehicule): VehiculeResource
    {
        return VehiculeResource::make($vehicule->load('carburant'));
    }

    public function update(VehiculeRequest $request, Vehicule $vehicule): VehiculeResource
    {
        $vehicule->update($request->validated());

        return VehiculeResource::make($vehicule->load('carburant'));
    }

    /**
     * Suppression réservée aux véhicules jamais servis à la cuve.
     *
     * Supprimer un véhicule qui a un historique effacerait des litres
     * réellement sortis de la cuve, et le stock ne correspondrait plus à
     * rien. Un véhicule qui quitte le parc se désactive, il ne s'efface pas.
     */
    public function destroy(Vehicule $vehicule): JsonResponse
    {
        if ($vehicule->sorties()->exists()) {
            return response()->json([
                'message' => sprintf(
                    'Suppression refusée : %s a un historique de pleins. Désactivez-le plutôt pour le retirer des saisies.',
                    $vehicule->designation,
                ),
            ], 409);
        }

        $vehicule->delete();

        return response()->json(status: 204);
    }

    /** Historique complet des pleins, véhicule par véhicule (§4). */
    public function historique(Request $request, Vehicule $vehicule): AnonymousResourceCollection
    {
        $sorties = $vehicule->sorties()
            ->with(['vehicule', 'chauffeur'])
            ->when(
                $request->filled('annee') && $request->filled('mois'),
                fn ($q) => $q->duMois($request->integer('annee'), $request->integer('mois')),
            )
            ->orderByDesc('date_sortie')
            ->orderByDesc('id')
            ->paginate($this->parPage($request, 50))
            ->withQueryString();

        return SortieResource::collection($sorties);
    }
}
