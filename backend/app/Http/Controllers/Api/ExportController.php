<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\ExportMensuel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Export Excel des totaux du mois (§4). */
class ExportController extends Controller
{
    public function __construct(private readonly ExportMensuel $export) {}

    public function mensuel(Request $request): BinaryFileResponse
    {
        $valide = $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $annee = (int) $valide['annee'];
        $mois = (int) $valide['mois'];

        $chemin = $this->export->generer($annee, $mois);

        // Le classeur est écrit dans un fichier temporaire : il est effacé
        // dès que la réponse est partie, pour ne pas accumuler des exports
        // sur le disque du serveur.
        return response()
            ->download($chemin, $this->export->nomFichier($annee, $mois))
            ->deleteFileAfterSend();
    }
}
