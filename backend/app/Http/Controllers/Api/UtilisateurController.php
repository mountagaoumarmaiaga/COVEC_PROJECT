<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\UtilisateurRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gestion des comptes, réservée au gestionnaire.
 *
 * C'est ici que se réattribue un mot de passe oublié : sans adresse
 * électronique, il n'y a pas de réinitialisation automatique par courriel.
 */
class UtilisateurController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->orderBy('nom')->get(),
        );
    }

    public function store(UtilisateurRequest $request): JsonResponse
    {
        $utilisateur = User::create($request->validated());

        return UserResource::make($utilisateur)->response()->setStatusCode(201);
    }

    public function show(User $utilisateur): UserResource
    {
        return UserResource::make($utilisateur);
    }

    public function update(UtilisateurRequest $request, User $utilisateur): UserResource
    {
        $valide = $request->validated();

        // Champ laissé vide en modification : le mot de passe actuel est
        // conservé plutôt que remplacé par une chaîne vide.
        if (blank($valide['password'] ?? null)) {
            unset($valide['password']);
        }

        $this->refuserSiDernierGestionnaire($request, $utilisateur, $valide);

        $utilisateur->update($valide);

        return UserResource::make($utilisateur);
    }

    public function destroy(Request $request, User $utilisateur): JsonResponse
    {
        if ($request->user()->is($utilisateur)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 409);
        }

        if ($this->estLeDernierGestionnaire($utilisateur)) {
            return response()->json([
                'message' => 'Ce compte est le dernier gestionnaire actif : sans lui, plus personne ne pourrait tenir le référentiel ni gérer les comptes.',
            ], 409);
        }

        $utilisateur->delete();

        return response()->json(status: 204);
    }

    /**
     * Empêche de rétrograder ou de désactiver le dernier gestionnaire actif.
     *
     * Sans ce garde-fou, une station peut se retrouver enfermée dehors : plus
     * aucun compte capable de tenir le référentiel ni de créer des comptes.
     *
     * @param  array<string, mixed>  $valide
     */
    private function refuserSiDernierGestionnaire(Request $request, User $utilisateur, array $valide): void
    {
        $perdSonRole = ($valide['role'] ?? $utilisateur->role->value) !== Role::Gestionnaire->value;
        $estDesactive = array_key_exists('actif', $valide) && ! $valide['actif'];

        if (! $perdSonRole && ! $estDesactive) {
            return;
        }

        if ($this->estLeDernierGestionnaire($utilisateur)) {
            abort(409, 'Ce compte est le dernier gestionnaire actif : gardez-lui son rôle, ou nommez d\'abord un autre gestionnaire.');
        }
    }

    private function estLeDernierGestionnaire(User $utilisateur): bool
    {
        if ($utilisateur->role !== Role::Gestionnaire || ! $utilisateur->actif) {
            return false;
        }

        return User::query()
            ->where('role', Role::Gestionnaire->value)
            ->where('actif', true)
            ->whereKeyNot($utilisateur->id)
            ->doesntExist();
    }
}
