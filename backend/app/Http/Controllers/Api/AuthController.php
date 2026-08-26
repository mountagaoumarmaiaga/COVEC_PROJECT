<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Connexion par matricule.
 *
 * Session par cookie plutôt que jeton en mémoire du navigateur : le cookie
 * est inaccessible au JavaScript, donc hors de portée d'une injection de
 * script. L'application mobile, elle, prendra un jeton Sanctum le moment venu
 * — les deux mécanismes cohabitent sans se gêner.
 */
class AuthController extends Controller
{
    public function connexion(Request $request): UserResource
    {
        $valide = $request->validate([
            'matricule' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], ['matricule' => 'matricule', 'password' => 'mot de passe']);

        // Garde nommé explicitement : la connexion par session appartient au
        // garde « web », et s'en remettre au garde par défaut le ferait
        // dépendre du contexte d'appel.
        if (! Auth::guard('web')->attempt($valide, $request->boolean('memoriser'))) {
            // Un seul message pour un matricule inconnu comme pour un mot de
            // passe faux : préciser lequel est en cause aiderait surtout
            // quelqu'un qui cherche des matricules valides.
            throw ValidationException::withMessages([
                'matricule' => 'Matricule ou mot de passe incorrect.',
            ]);
        }

        // Le compte désactivé est signalé explicitement : à la station, une
        // personne dont l'accès a été retiré doit comprendre pourquoi plutôt
        // que de retaper son mot de passe dix fois.
        $connecte = Auth::guard('web')->user();

        if (! $connecte->actif) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'matricule' => 'Ce compte est désactivé. Adressez-vous au gestionnaire.',
            ]);
        }

        // La régénération n'a de sens que pour un client à session — le
        // navigateur. Un client à jeton, l'application mobile demain, passe
        // par la même route sans session attachée à la requête.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return UserResource::make($connecte);
    }

    public function deconnexion(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(status: 204);
    }

    /** Compte connecté, interrogé au démarrage de l'interface. */
    public function moi(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function changerMotDePasse(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'actuel' => ['required', 'string'],
            'nouveau' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'nouveau.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'nouveau.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ]);

        // Vérification directe du haché plutôt que passage par un garde : il
        // ne s'agit pas d'authentifier quelqu'un, mais de confirmer que la
        // personne devant l'écran est bien celle dont la session est ouverte.
        if (! Hash::check($valide['actuel'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'actuel' => 'Mot de passe actuel incorrect.',
            ]);
        }

        $request->user()->update(['password' => $valide['nouveau']]);

        return response()->json(['message' => 'Mot de passe modifié.']);
    }
}
