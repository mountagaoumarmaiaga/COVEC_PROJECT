<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\ExportMensuel;
use App\Exports\RapportMensuel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Documents mensuels (§4).
 *
 * Deux formats pour deux usages : le classeur pour retravailler les chiffres,
 * le rapport pour les transmettre ou les archiver. Tous deux lisent la même
 * source, si bien qu'ils ne peuvent pas se contredire.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly ExportMensuel $classeur,
        private readonly RapportMensuel $rapport,
    ) {}

    /** Classeur Excel : cinq feuilles, filtrables et retravaillables. */
    public function mensuel(Request $request): BinaryFileResponse
    {
        [$annee, $mois] = $this->periode($request);

        return $this->livrer(
            $this->classeur->generer($annee, $mois),
            $this->classeur->nomFichier($annee, $mois),
        );
    }

    /** Rapport PDF : mise en page fixe, prêt à imprimer ou à envoyer. */
    public function rapport(Request $request): BinaryFileResponse
    {
        [$annee, $mois] = $this->periode($request);

        return $this->livrer(
            $this->rapport->generer($annee, $mois),
            $this->rapport->nomFichier($annee, $mois),
        );
    }

    /** @return array{0: int, 1: int} */
    private function periode(Request $request): array
    {
        $valide = $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return [(int) $valide['annee'], (int) $valide['mois']];
    }

    /**
     * Envoie le document puis efface le fichier temporaire.
     *
     * Sans cet effacement, chaque export laisserait sa copie sur le disque du
     * serveur — et un hébergeur au disque éphémère la perdrait de toute façon
     * au déploiement suivant.
     */
    private function livrer(string $chemin, string $nom): BinaryFileResponse
    {
        return response()->download($chemin, $nom)->deleteFileAfterSend();
    }
}
