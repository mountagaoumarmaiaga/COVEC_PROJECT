<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Taille de page par défaut des listes. */
    protected const PAR_PAGE = 25;

    /** Plafond : au-delà, une page coûte plus cher qu'elle ne rend service. */
    protected const PAR_PAGE_MAX = 200;

    /**
     * Taille de page demandée, ramenée dans des bornes raisonnables.
     *
     * Sans plafond, « par_page=999999 » ferait charger la table entière en
     * mémoire à chaque appel — une adresse d'API publique ne doit pas offrir
     * ce levier.
     */
    protected function parPage(Request $request, ?int $defaut = null): int
    {
        $demande = $request->integer('par_page', $defaut ?? static::PAR_PAGE);

        return max(1, min($demande, static::PAR_PAGE_MAX));
    }
}
