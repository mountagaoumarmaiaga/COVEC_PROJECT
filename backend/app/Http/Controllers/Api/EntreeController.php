<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntreeRequest;
use App\Http\Resources\EntreeResource;
use App\Models\Entree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Écran 1 « Entrées » (§2) : remplissage de la cuve. */
class EntreeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entrees = Entree::query()
            ->with('carburant')
            ->when(
                $request->filled('annee') && $request->filled('mois'),
                fn ($q) => $q->duMois($request->integer('annee'), $request->integer('mois')),
            )
            ->when(
                $request->filled('fournisseur'),
                fn ($q) => $q->where('fournisseur', 'like', '%'.$request->string('fournisseur').'%'),
            )
            ->orderByDesc('date_entree')
            ->orderByDesc('id')
            ->paginate($request->integer('par_page', 25))
            ->withQueryString();

        return EntreeResource::collection($entrees);
    }

    public function store(EntreeRequest $request): JsonResponse
    {
        $entree = Entree::create($request->validated());
        $entree->load('carburant');

        return EntreeResource::make($entree)->response()->setStatusCode(201);
    }

    public function show(Entree $entree): EntreeResource
    {
        return EntreeResource::make($entree->load('carburant'));
    }

    public function update(EntreeRequest $request, Entree $entree): EntreeResource
    {
        $entree->update($request->validated());

        return EntreeResource::make($entree->load('carburant'));
    }

    public function destroy(Entree $entree): JsonResponse
    {
        $entree->delete();

        return response()->json(status: 204);
    }
}
