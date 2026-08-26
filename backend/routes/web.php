<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Service de l'interface
|--------------------------------------------------------------------------
|
| Laravel sert lui-même l'interface React, compilée dans public/app. Front et
| API partagent ainsi la même origine, ce qui est la condition de
| l'authentification par cookie : un cookie de session en SameSite=Lax ne
| voyage pas d'un domaine à l'autre.
|
| Toutes les adresses non reconnues retombent sur index.html, parce que la
| navigation est côté navigateur : /sorties n'existe pas côté serveur, c'est
| le routeur React qui en décide une fois la page chargée.
|
*/

Route::fallback(function (Request $request) {
    // L'API garde ses propres 404, en JSON : renvoyer la page d'accueil à un
    // appel d'API donnerait au client du HTML là où il attend des données.
    if ($request->is('api/*') || $request->is('sanctum/*')) {
        abort(404, 'Cette adresse d’API n’existe pas.');
    }

    $interface = public_path('app/index.html');

    abort_unless(
        file_exists($interface),
        404,
        'L’interface n’a pas encore été compilée. Lancez « npm run build » dans web/.',
    );

    return response()->file($interface);
});
