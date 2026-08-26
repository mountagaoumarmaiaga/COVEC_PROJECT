<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint une route à certains rôles.
 *
 * Appliqué au niveau des routes plutôt que dans chaque contrôleur : la
 * matrice des permissions se lit alors d'un seul coup d'œil dans
 * routes/api.php, au lieu d'être dispersée dans une dizaine de méthodes.
 *
 *     Route::apiResource(...)->middleware('role:gestionnaire');
 */
class RoleRequis
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur === null) {
            abort(401, 'Connexion requise.');
        }

        if (! $utilisateur->actif) {
            abort(403, 'Ce compte est désactivé.');
        }

        if (! in_array($utilisateur->role->value, $roles, true)) {
            abort(403, sprintf(
                'Action réservée : votre rôle « %s » ne permet pas cette opération.',
                $utilisateur->role->libelle(),
            ));
        }

        return $next($request);
    }
}
