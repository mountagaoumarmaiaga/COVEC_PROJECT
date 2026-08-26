<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Entree;
use App\Models\Sortie;
use App\Services\StockService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Export Excel du mois (§4 du cahier des charges).
 *
 * Quatre onglets : la synthèse que le gestionnaire regarde en premier — une
 * colonne par carburant, puisque les litres de gasoil et d'essence ne
 * s'additionnent pas — puis les deux journaux de mouvements, puis la
 * consommation par véhicule.
 *
 * Écrit avec openspout plutôt que PhpSpreadsheet, qui exige l'extension GD
 * absente de l'installation PHP de la station.
 */
class ExportMensuel
{
    private const MOIS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    public function __construct(private readonly StockService $stock) {}

    public function nomFichier(int $annee, int $mois): string
    {
        return sprintf('carburant-covec-%04d-%02d.xlsx', $annee, $mois);
    }

    /** Génère le classeur et renvoie le chemin du fichier temporaire produit. */
    public function generer(int $annee, int $mois): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'covec_').'.xlsx';

        $writer = new Writer();
        $writer->openToFile($chemin);

        $this->ongletSynthese($writer, $annee, $mois);
        $this->ongletEntrees($writer, $annee, $mois);
        $this->ongletSorties($writer, $annee, $mois);
        $this->ongletParVehicule($writer, $annee, $mois);

        $writer->close();

        return $chemin;
    }

    private function titre(): Style
    {
        return new Style(fontBold: true, fontSize: 14);
    }

    private function entete(): Style
    {
        return new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '1F3864');
    }

    private function gras(): Style
    {
        return new Style(fontBold: true);
    }

    /** Les pleins signalés en rouge le restent dans le fichier exporté. */
    private function ligneAnomalie(): Style
    {
        return new Style(fontColor: '9C0006', backgroundColor: 'FFC7CE');
    }

    private function periode(int $annee, int $mois): string
    {
        return sprintf('%s %d', self::MOIS[$mois] ?? (string) $mois, $annee);
    }

    private function ongletSynthese(Writer $writer, int $annee, int $mois): void
    {
        $writer->getCurrentSheet()->setName('Synthèse');

        $synthese = $this->stock->synthese();
        $totaux = $this->stock->totauxMois($annee, $mois);

        $writer->addRow(Row::fromValuesWithStyle(
            ['Suivi du carburant COVEC — '.$this->periode($annee, $mois)],
            $this->titre(),
        ));
        $writer->addRow(Row::fromValues([]));

        foreach ($synthese['carburants'] as $bilan) {
            $writer->addRow(Row::fromValuesWithStyle(
                [$bilan['carburant']['libelle'], ''],
                $this->entete(),
            ));
            $writer->addRow(Row::fromValues(['Cuve', $bilan['cuve']['nom']]));
            $writer->addRow(Row::fromValues(['Capacité (L)', $bilan['cuve']['capacite']]));
            $writer->addRow(Row::fromValues(['Stock actuel (L)', $bilan['stock_actuel']]));
            $writer->addRow(Row::fromValues(['Taux de remplissage (%)', $bilan['taux_remplissage']]));
            $writer->addRow(Row::fromValues(['Prix du litre en vigueur', $bilan['carburant']['prix_par_defaut']]));
            $writer->addRow(Row::fromValues(['Prix moyen pondéré des achats', $bilan['prix_moyen_pondere']]));
            $writer->addRow(Row::fromValues(['Total entrées depuis l\'origine (L)', $bilan['total_entrees']]));
            $writer->addRow(Row::fromValues(['Total sorties depuis l\'origine (L)', $bilan['total_sorties']]));
            $writer->addRow(Row::fromValues(['Pleins signalés', $bilan['nombre_pleins_anormaux']]));
            $writer->addRow(Row::fromValues([]));
        }

        $writer->addRow(Row::fromValuesWithStyle(
            ['Totaux de '.$this->periode($annee, $mois), '', '', '', ''],
            $this->entete(),
        ));
        $writer->addRow(Row::fromValuesWithStyle(
            ['Carburant', 'Livraisons (L)', 'Montant achats', 'Pleins (L)', 'Montant pleins'],
            $this->gras(),
        ));

        foreach ($totaux['carburants'] as $ligne) {
            $writer->addRow(Row::fromValues([
                $ligne['carburant']['libelle'],
                $ligne['entrees']['litres'],
                $ligne['entrees']['montant'],
                $ligne['sorties']['litres'],
                $ligne['sorties']['montant'],
            ]));
        }

        // Seuls les montants se totalisent toutes cuves confondues : additionner
        // des litres de gasoil et d'essence ne correspondrait à aucun réservoir.
        $writer->addRow(Row::fromValuesWithStyle([
            'Ensemble',
            '',
            $totaux['ensemble']['entrees']['montant'],
            '',
            $totaux['ensemble']['sorties']['montant'],
        ], $this->gras()));
    }

    private function ongletEntrees(Writer $writer, int $annee, int $mois): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName('Entrées');

        $writer->addRow(Row::fromValuesWithStyle(
            ['Date et heure', 'Carburant', 'Fournisseur', 'N° de bon', 'Quantité (L)', 'Prix unitaire', 'Montant'],
            $this->entete(),
        ));

        $entrees = Entree::query()
            ->with('carburant')
            ->duMois($annee, $mois)
            ->orderBy('date_entree')
            ->orderBy('id')
            ->get();

        foreach ($entrees as $entree) {
            $writer->addRow(Row::fromValues([
                $entree->date_entree->format('d/m/Y H:i'),
                $entree->carburant?->libelle ?? '',
                $entree->fournisseur,
                $entree->reference_bon ?? '',
                $entree->quantite_litres,
                $entree->prix_unitaire,
                $entree->montant,
            ]));
        }

        $writer->addRow(Row::fromValuesWithStyle([
            'Total', '', '', '',
            round((float) $entrees->sum('quantite_litres'), 2),
            '',
            round((float) $entrees->sum('montant'), 2),
        ], $this->gras()));
    }

    private function ongletSorties(Writer $writer, int $annee, int $mois): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName('Sorties');

        $writer->addRow(Row::fromValuesWithStyle([
            'Date et heure', 'Code', 'Véhicule', 'Carburant', 'Chauffeur',
            'Litres servis', 'Prix du litre', 'Montant',
            'Index compteur', 'Unité index', 'Distance parcourue',
            'Consommation', 'Unité consommation', 'Moyenne du véhicule',
            'Écart (%)', 'Plein signalé',
        ], $this->entete()));

        $sorties = Sortie::query()
            ->with(['vehicule.carburant', 'chauffeur'])
            ->duMois($annee, $mois)
            ->chronologique()
            ->get();

        foreach ($sorties as $sortie) {
            $mode = $sortie->vehicule->mode_suivi;

            $valeurs = [
                $sortie->date_sortie->format('d/m/Y H:i'),
                $sortie->vehicule->code,
                $sortie->vehicule->designation,
                $sortie->vehicule->carburant?->libelle ?? '',
                $sortie->chauffeur->nom,
                $sortie->litres_servis,
                $sortie->prix_unitaire,
                $sortie->montant,
                $sortie->index_compteur,
                $mode->uniteIndex(),
                $sortie->distance_parcourue ?? '',
                $sortie->consommation ?? '',
                $mode->uniteConsommation(),
                $sortie->moyenne_reference ?? '',
                $sortie->ecart_pourcentage ?? '',
                $sortie->anomalie ? 'OUI' : '',
            ];

            $writer->addRow($sortie->anomalie
                ? Row::fromValuesWithStyle($valeurs, $this->ligneAnomalie())
                : Row::fromValues($valeurs));
        }

        $writer->addRow(Row::fromValuesWithStyle([
            'Total', '', '', '', '',
            round((float) $sorties->sum('litres_servis'), 2),
            '',
            round((float) $sorties->sum('montant'), 2),
        ], $this->gras()));
    }

    private function ongletParVehicule(Writer $writer, int $annee, int $mois): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName('Consommation par véhicule');

        $writer->addRow(Row::fromValuesWithStyle([
            'Code', 'Désignation', 'Carburant', 'Mode de suivi', 'Nombre de pleins',
            'Litres servis', 'Montant', 'Distance parcourue',
            'Consommation moyenne', 'Unité', 'Pleins signalés',
        ], $this->entete()));

        foreach ($this->stock->consommationParVehicule($annee, $mois) as $ligne) {
            $writer->addRow(Row::fromValues([
                $ligne['vehicule']['code'],
                $ligne['vehicule']['designation'],
                $ligne['vehicule']['carburant']['libelle'] ?? '',
                $ligne['vehicule']['mode_suivi'],
                $ligne['nombre_pleins'],
                $ligne['litres_servis'],
                $ligne['montant'],
                $ligne['distance_totale'],
                $ligne['moyenne_consommation'] ?? '',
                $ligne['vehicule']['unite_consommation'],
                $ligne['nombre_anomalies'],
            ]));
        }
    }
}
