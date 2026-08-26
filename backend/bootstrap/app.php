<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
          Sanctum en mode SPA : les requêtes venant de l'interface web sont
          authentifiées par le cookie de session, inaccessible au JavaScript,
          plutôt que par un jeton rangé dans le navigateur. L'application
          mobile prendra un jeton Sanctum le moment venu — les deux mécanismes
          cohabitent sur les mêmes routes.
        */
        $middleware->statefulApi();

        // Plafond général, défini dans AppServiceProvider. Assez large pour ne
        // jamais gêner une saisie normale, assez bas pour plafonner
        // l'aspiration de tout l'historique.
        $middleware->api(append: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleRequis::class,
        ]);

        /*
          Où envoyer un visiteur non connecté.

          Sur une adresse d'interface, vers la racine : l'application y affiche
          son écran de connexion. Sur une adresse d'API, nulle part — renvoyer
          null fait remonter un 401, alors que chercher une route « login »
          inexistante produisait une erreur 500 pour un simple appel non
          authentifié.
        */
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson() ? null : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Le message par défaut de Laravel est en anglais. Celui-ci s'affiche
        // à un pompiste qui s'est trompé de mot de passe : il doit lui dire en
        // français combien de temps attendre.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $attente = (int) ($e->getHeaders()['Retry-After'] ?? 0);

            return response()->json([
                'message' => $attente > 0
                    ? sprintf('Trop de tentatives. Réessayez dans %d seconde%s.', $attente, $attente > 1 ? 's' : '')
                    : 'Trop de tentatives. Réessayez dans un instant.',
            ], 429, $e->getHeaders());
        });
    })->create();
