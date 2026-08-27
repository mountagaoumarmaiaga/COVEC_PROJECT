<?php

declare(strict_types=1);

namespace App\Exports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

/**
 * Rapport mensuel au format PDF.
 *
 * Le classeur sert à retravailler les chiffres ; celui-ci sert à les
 * transmettre. Un tableur ouvert sur un autre poste ne s'affiche jamais tout à
 * fait comme chez soi — largeurs de colonnes, polices, séparateurs décimaux
 * changent — alors qu'un rapport imprimé ou envoyé doit rester identique.
 *
 * Les deux documents lisent [[DonneesMensuelles]] : ils ne peuvent donc pas
 * afficher des totaux différents.
 */
class RapportMensuel
{
    public function __construct(private readonly DonneesMensuelles $donnees) {}

    public function nomFichier(int $annee, int $mois): string
    {
        return sprintf('rapport-carburant-covec-%04d-%02d.pdf', $annee, $mois);
    }

    /** Génère le rapport et renvoie le chemin du fichier temporaire produit. */
    public function generer(int $annee, int $mois): string
    {
        $html = View::make('rapports.mensuel', $this->donnees->pour($annee, $mois))->render();

        $options = new Options();
        // DejaVu Sans porte les accents français ; la police par défaut de
        // dompdf les rend en caractères vides.
        $options->set('defaultFont', 'DejaVu Sans');
        // Aucune ressource distante : le rapport ne doit dépendre d'aucun
        // réseau au moment où on l'imprime.
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $chemin = tempnam(sys_get_temp_dir(), 'covec_').'.pdf';
        file_put_contents($chemin, $dompdf->output());

        return $chemin;
    }
}
