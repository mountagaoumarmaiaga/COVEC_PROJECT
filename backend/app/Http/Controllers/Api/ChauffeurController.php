<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChauffeurRequest;
use App\Http\Resources\ChauffeurResource;
use App\Models\Chauffeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Référentiel « Chauffeurs » (§3). */
class ChauffeurController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $chauffeurs = Chauffeur::query()
            ->when($request->boolean('actifs_seulement'), fn ($q) => $q->actifs())
            ->orderBy('nom')
            ->get();

        return ChauffeurResource::collection($chauffeurs);
    }

    public function store(ChauffeurRequest $request): JsonResponse
    {
        $chauffeur = Chauffeur::create($request->validated());

        return ChauffeurResource::make($chauffeur)->response()->setStatusCode(201);
    }

    public function show(Chauffeur $chauffeur): ChauffeurResource
    {
        return ChauffeurResource::make($chauffeur);
    }

    public function update(ChauffeurRequest $request, Chauffeur $chauffeur): ChauffeurResource
    {
        $chauffeur->update($request->validated());

        return ChauffeurResource::make($chauffeur);
    }

    /** Même principe que pour les véhicules : on désactive, on n'efface pas un historique. */
    public function destroy(Chauffeur $chauffeur): JsonResponse
    {
        if ($chauffeur->sorties()->exists()) {
            return response()->json([
                'message' => sprintf(
                    'Suppression refusée : %s a servi des pleins. Désactivez-le plutôt pour le retirer des saisies.',
                    $chauffeur->nom,
                ),
            ], 409);
        }

        $chauffeur->delete();

        return response()->json(status: 204);
    }
}
