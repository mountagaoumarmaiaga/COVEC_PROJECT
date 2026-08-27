<?php

declare(strict_types=1);

namespace App\Exports;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Classeur mensuel (§4 du cahier des charges).
 *
 * Cinq feuilles : la synthèse que le gestionnaire ouvre en premier, les deux
 * journaux de mouvements, puis les récapitulatifs par véhicule et par
 * chauffeur.
 *
 * Le soin apporté à la mise en forme n'est pas décoratif. Ce fichier sort de
 * la station : il est transmis, imprimé, archivé. Des colonnes trop étroites
 * affichent « ##### » à la place d'un montant, une ligne d'en-tête qui défile
 * fait perdre le sens des colonnes au bout de trente lignes, et un nombre sans
 * séparateur de milliers se lit mal. Chacun de ces détails coûte du temps à
 * qui reçoit le document.
 *
 * Écrit avec openspout plutôt que PhpSpreadsheet, qui exige l'extension GD
 * absente de l'installation PHP de la station.
 */
class ExportMensuel
{
    /** Bleu d'en-tête, repris du document imprimé. */
    private const ENCRE = '1F3864';

    private const ARDOISE = 'E7EBF3';

    public function __construct(private readonly DonneesMensuelles $donnees) {}

    public function nomFichier(int $annee, int $mois): string
    {
        return sprintf('carburant-covec-%04d-%02d.xlsx', $annee, $mois);
    }

    /** Génère le classeur et renvoie le chemin du fichier temporaire produit. */
    public function generer(int $annee, int $mois): string
    {
        $donnees = $this->donnees->pour($annee, $mois);
        $chemin = tempnam(sys_get_temp_dir(), 'covec_').'.xlsx';

        // Les propriétés du document sont en lecture seule une fois l'objet
        // construit : elles se passent donc au constructeur.
        $options = new Options(
            properties: new Properties(
                title: 'Suivi du carburant COVEC — '.$donnees['periode'],
                subject: 'Entrées, sorties, stock et consommation',
                application: 'COVEC',
                creator: 'COVEC',
                lastModifiedBy: 'COVEC',
                category: 'Registre carburant',
                language: 'fr-FR',
            ),
        );

        $writer = new Writer($options);
        $writer->openToFile($chemin);

        $this->feuilleSynthese($writer, $donnees);
        $this->feuilleEntrees($writer, $donnees);
        $this->feuilleSorties($writer, $donnees);
        $this->feuilleParVehicule($writer, $donnees);
        $this->feuilleParChauffeur($writer, $donnees);

        $writer->close();

        return $chemin;
    }

    // -----------------------------------------------------------------------
    // Vocabulaire visuel
    // -----------------------------------------------------------------------

    private function titre(): Style
    {
        return new Style(fontBold: true, fontSize: 16, fontColor: self::ENCRE, fontName: 'Calibri');
    }

    private function sousTitre(): Style
    {
        return new Style(fontSize: 10, fontColor: '6B7280');
    }

    /** Bandeau d'en-tête d'un tableau : blanc sur bleu, cerné. */
    private function entete(): Style
    {
        return new Style(
            fontBold: true,
            fontColor: 'FFFFFF',
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            border: $this->cadre('FFFFFF'),
            backgroundColor: self::ENCRE,
        );
    }

    /** Intitulé de section, dans la feuille de synthèse. */
    private function section(): Style
    {
        return new Style(
            fontBold: true,
            fontColor: self::ENCRE,
            border: $this->cadre('C7D0E3'),
            backgroundColor: self::ARDOISE,
        );
    }

    private function libelle(): Style
    {
        return new Style(fontColor: '374151');
    }

    private function total(): Style
    {
        return new Style(
            fontBold: true,
            border: new Border(new BorderPart(BorderName::TOP, self::ENCRE)),
            backgroundColor: self::ARDOISE,
        );
    }

    /** Les pleins signalés en rouge à l'écran le restent dans le fichier. */
    private function anomalie(): Style
    {
        return new Style(fontColor: '9C0006', backgroundColor: 'FFC7CE');
    }

    private function cadre(string $couleur): Border
    {
        return new Border(
            new BorderPart(BorderName::TOP, $couleur),
            new BorderPart(BorderName::BOTTOM, $couleur),
            new BorderPart(BorderName::LEFT, $couleur),
            new BorderPart(BorderName::RIGHT, $couleur),
        );
    }

    /*
      Formats de nombre.

      Excel n'affiche « 12 450 » que si on le lui demande : sans format, la
      cellule montre 12450, et un montant à sept chiffres devient illisible.
      Le franc CFA n'a pas de décimale — les afficher donnerait une précision
      que le prix à la pompe n'a pas.
    */
    private function litres(): Style
    {
        return new Style(format: '#,##0.00');
    }

    private function francs(): Style
    {
        return new Style(format: '#,##0 "F"');
    }

    private function pourcentage(): Style
    {
        return new Style(format: '0.0"%"');
    }

    private function entier(): Style
    {
        return new Style(format: '#,##0');
    }

    // -----------------------------------------------------------------------
    // Feuilles
    // -----------------------------------------------------------------------

    private function feuilleSynthese(Writer $writer, array $d): void
    {
        $feuille = $writer->getCurrentSheet();
        $feuille->setName('Synthèse');
        $feuille->setColumnWidth(34, 1);
        $feuille->setColumnWidth(18, 2, 3, 4, 5);

        $writer->addRow(Row::fromValuesWithStyle(['COVEC — Suivi du carburant'], $this->titre()));
        $writer->addRow(Row::fromValuesWithStyle([$d['periode'].' · édité le '.$d['editee_le']], $this->sousTitre()));
        $writer->addRow(Row::fromValues([]));

        foreach ($d['synthese']['carburants'] as $bilan) {
            $writer->addRow(Row::fromValuesWithStyle(
                [mb_strtoupper($bilan['carburant']['libelle']), '', '', '', ''],
                $this->section(),
            ));

            $this->paire($writer, 'Cuve', $bilan['cuve']['nom']);
            $this->paire($writer, 'Capacité', $bilan['cuve']['capacite'], $this->litres());
            $this->paire($writer, 'Stock actuel', $bilan['stock_actuel'], $this->litres());
            $this->paire($writer, 'Taux de remplissage', $bilan['taux_remplissage'], $this->pourcentage());
            $this->paire($writer, 'Prix du litre en vigueur', $bilan['carburant']['prix_par_defaut'], $this->francs());
            $this->paire($writer, 'Prix moyen pondéré des achats', $bilan['prix_moyen_pondere'], $this->francs());
            $this->paire($writer, 'Total reçu depuis l\'origine', $bilan['total_entrees'], $this->litres());
            $this->paire($writer, 'Total servi depuis l\'origine', $bilan['total_sorties'], $this->litres());
            $this->paire($writer, 'Pleins signalés', $bilan['nombre_pleins_anormaux'], $this->entier());

            $writer->addRow(Row::fromValues([]));
        }

        $writer->addRow(Row::fromValuesWithStyle(
            ['MOUVEMENTS '.mb_strtoupper($d['de_la_periode']), '', '', '', ''],
            $this->section(),
        ));
        $writer->addRow(Row::fromValuesWithStyle(
            ['Carburant', 'Reçu (L)', 'Montant des achats', 'Servi (L)', 'Montant des pleins'],
            $this->entete(),
        ));

        foreach ($d['totaux']['carburants'] as $ligne) {
            $writer->addRow(new Row([
                Cell::fromValue($ligne['carburant']['libelle']),
                Cell::fromValue($ligne['entrees']['litres'], $this->litres()),
                Cell::fromValue($ligne['entrees']['montant'], $this->francs()),
                Cell::fromValue($ligne['sorties']['litres'], $this->litres()),
                Cell::fromValue($ligne['sorties']['montant'], $this->francs()),
            ]));
        }

        // Seuls les montants se totalisent toutes cuves confondues : additionner
        // des litres de gasoil et d'essence ne correspondrait à aucun réservoir.
        $writer->addRow(new Row([
            Cell::fromValue('Ensemble', $this->total()),
            Cell::fromValue('—', $this->total()),
            Cell::fromValue($d['totaux']['ensemble']['entrees']['montant'], new Style(fontBold: true, format: '#,##0 "F"', backgroundColor: self::ARDOISE)),
            Cell::fromValue('—', $this->total()),
            Cell::fromValue($d['totaux']['ensemble']['sorties']['montant'], new Style(fontBold: true, format: '#,##0 "F"', backgroundColor: self::ARDOISE)),
        ]));

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(
            ['Les litres ne s\'additionnent qu\'à l\'intérieur d\'un carburant : une cuve de gasoil et une cuve d\'essence sont deux réservoirs distincts.'],
            $this->sousTitre(),
        ));
    }

    private function paire(Writer $writer, string $libelle, mixed $valeur, ?Style $format = null): void
    {
        $writer->addRow(new Row([
            Cell::fromValue($libelle, $this->libelle()),
            Cell::fromValue($valeur, $format),
        ]));
    }

    private function feuilleEntrees(Writer $writer, array $d): void
    {
        $feuille = $writer->addNewSheetAndMakeItCurrent();
        $feuille->setName('Livraisons');
        $feuille->setColumnWidth(18, 1);
        $feuille->setColumnWidth(14, 2);
        $feuille->setColumnWidth(26, 3);
        $feuille->setColumnWidth(16, 4);
        $feuille->setColumnWidth(14, 5, 6, 7);
        $this->figerEntete($feuille);

        $writer->addRow(Row::fromValuesWithStyle(
            ['Date et heure', 'Carburant', 'Fournisseur', 'N° de bon', 'Quantité (L)', 'Prix du litre', 'Montant'],
            $this->entete(),
        ));

        foreach ($d['entrees'] as $entree) {
            $writer->addRow(new Row([
                Cell::fromValue($entree->date_entree->format('d/m/Y H:i')),
                Cell::fromValue($entree->carburant?->libelle ?? ''),
                Cell::fromValue($entree->fournisseur),
                Cell::fromValue($entree->reference_bon ?? ''),
                Cell::fromValue($entree->quantite_litres, $this->litres()),
                Cell::fromValue($entree->prix_unitaire, $this->francs()),
                Cell::fromValue($entree->montant, $this->francs()),
            ]));
        }

        $this->filtre($feuille, 7, $d['entrees']->count());

        $writer->addRow(new Row([
            Cell::fromValue($d['totaux_entrees']['nombre'].' livraison(s)', $this->total()),
            Cell::fromValue('', $this->total()),
            Cell::fromValue('', $this->total()),
            Cell::fromValue('Total', $this->total()),
            Cell::fromValue($d['totaux_entrees']['litres'], new Style(fontBold: true, format: '#,##0.00', backgroundColor: self::ARDOISE)),
            Cell::fromValue('', $this->total()),
            Cell::fromValue($d['totaux_entrees']['montant'], new Style(fontBold: true, format: '#,##0 "F"', backgroundColor: self::ARDOISE)),
        ]));
    }

    private function feuilleSorties(Writer $writer, array $d): void
    {
        $feuille = $writer->addNewSheetAndMakeItCurrent();
        $feuille->setName('Pleins servis');
        $feuille->setColumnWidth(18, 1);
        $feuille->setColumnWidth(11, 2);
        $feuille->setColumnWidth(32, 3);
        $feuille->setColumnWidth(13, 4);
        $feuille->setColumnWidth(22, 5);
        $feuille->setColumnWidth(13, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15);
        $feuille->setColumnWidth(14, 16);
        $this->figerEntete($feuille);

        $writer->addRow(Row::fromValuesWithStyle([
            'Date et heure', 'Code', 'Véhicule', 'Carburant', 'Chauffeur',
            'Litres servis', 'Prix du litre', 'Montant',
            'Index compteur', 'Unité', 'Distance', 'Consommation', 'Unité',
            'Moyenne du véhicule', 'Écart', 'Plein signalé',
        ], $this->entete()));

        foreach ($d['sorties'] as $sortie) {
            $mode = $sortie->vehicule->mode_suivi;
            $rouge = $sortie->anomalie;

            $texte = fn (mixed $v) => Cell::fromValue($v, $rouge ? $this->anomalie() : null);
            $nombre = fn (mixed $v, string $f) => Cell::fromValue(
                $v,
                $rouge
                    ? new Style(fontColor: '9C0006', backgroundColor: 'FFC7CE', format: $f)
                    : new Style(format: $f),
            );

            $writer->addRow(new Row([
                $texte($sortie->date_sortie->format('d/m/Y H:i')),
                $texte($sortie->vehicule->code),
                $texte($sortie->vehicule->designation),
                $texte($sortie->vehicule->carburant?->libelle ?? ''),
                $texte($sortie->chauffeur->nom),
                $nombre($sortie->litres_servis, '#,##0.00'),
                $nombre($sortie->prix_unitaire, '#,##0 "F"'),
                $nombre($sortie->montant, '#,##0 "F"'),
                $nombre($sortie->index_compteur, '#,##0'),
                $texte($mode->uniteIndex()),
                $sortie->distance_parcourue === null ? $texte('') : $nombre($sortie->distance_parcourue, '#,##0'),
                $sortie->consommation === null ? $texte('') : $nombre($sortie->consommation, '#,##0.00'),
                $texte($mode->uniteConsommation()),
                $sortie->moyenne_reference === null ? $texte('') : $nombre($sortie->moyenne_reference, '#,##0.00'),
                $sortie->ecart_pourcentage === null ? $texte('') : $nombre($sortie->ecart_pourcentage, '+0.0"%";-0.0"%"'),
                $texte($rouge ? 'OUI' : ''),
            ]));
        }

        $this->filtre($feuille, 16, $d['sorties']->count());

        $writer->addRow(new Row([
            Cell::fromValue($d['totaux_sorties']['nombre'].' plein(s)', $this->total()),
            ...array_map(
                fn () => Cell::fromValue('', $this->total()),
                range(1, 3),
            ),
            Cell::fromValue('Total', $this->total()),
            Cell::fromValue($d['totaux_sorties']['litres'], new Style(fontBold: true, format: '#,##0.00', backgroundColor: self::ARDOISE)),
            Cell::fromValue('', $this->total()),
            Cell::fromValue($d['totaux_sorties']['montant'], new Style(fontBold: true, format: '#,##0 "F"', backgroundColor: self::ARDOISE)),
            ...array_map(
                fn () => Cell::fromValue('', $this->total()),
                range(1, 7),
            ),
            Cell::fromValue($d['totaux_sorties']['anomalies'], new Style(fontBold: true, format: '#,##0', backgroundColor: self::ARDOISE)),
        ]));
    }

    private function feuilleParVehicule(Writer $writer, array $d): void
    {
        $feuille = $writer->addNewSheetAndMakeItCurrent();
        $feuille->setName('Par véhicule');
        $feuille->setColumnWidth(11, 1);
        $feuille->setColumnWidth(34, 2);
        $feuille->setColumnWidth(13, 3, 4);
        $feuille->setColumnWidth(14, 5, 6, 7, 8);
        $feuille->setColumnWidth(20, 9);
        $feuille->setColumnWidth(13, 10, 11);
        $this->figerEntete($feuille);

        $writer->addRow(Row::fromValuesWithStyle([
            'Code', 'Désignation', 'Carburant', 'Suivi', 'Pleins',
            'Litres servis', 'Montant', 'Distance',
            'Consommation moyenne', 'Unité', 'Signalés',
        ], $this->entete()));

        foreach ($d['par_vehicule'] as $ligne) {
            $writer->addRow(new Row([
                Cell::fromValue($ligne['vehicule']['code']),
                Cell::fromValue($ligne['vehicule']['designation']),
                Cell::fromValue($ligne['vehicule']['carburant']['libelle'] ?? ''),
                Cell::fromValue($ligne['vehicule']['mode_suivi']),
                Cell::fromValue($ligne['nombre_pleins'], $this->entier()),
                Cell::fromValue($ligne['litres_servis'], $this->litres()),
                Cell::fromValue($ligne['montant'], $this->francs()),
                Cell::fromValue($ligne['distance_totale'], $this->entier()),
                $ligne['moyenne_consommation'] === null
                    ? Cell::fromValue('—')
                    : Cell::fromValue($ligne['moyenne_consommation'], $this->litres()),
                Cell::fromValue($ligne['vehicule']['unite_consommation']),
                Cell::fromValue($ligne['nombre_anomalies'], $this->entier()),
            ]));
        }

        $this->filtre($feuille, 11, $d['par_vehicule']->count());
    }

    private function feuilleParChauffeur(Writer $writer, array $d): void
    {
        $feuille = $writer->addNewSheetAndMakeItCurrent();
        $feuille->setName('Par chauffeur');
        $feuille->setColumnWidth(28, 1);
        $feuille->setColumnWidth(14, 2, 3);
        $feuille->setColumnWidth(16, 4, 5);
        $feuille->setColumnWidth(34, 6);
        $feuille->setColumnWidth(13, 7);
        $this->figerEntete($feuille);

        $writer->addRow(Row::fromValuesWithStyle(
            ['Chauffeur', 'Matricule', 'Pleins', 'Litres servis', 'Montant', 'Véhicules conduits', 'Signalés'],
            $this->entete(),
        ));

        foreach ($d['par_chauffeur'] as $ligne) {
            $writer->addRow(new Row([
                Cell::fromValue($ligne['chauffeur']['nom']),
                Cell::fromValue($ligne['chauffeur']['matricule']),
                Cell::fromValue($ligne['nombre_pleins'], $this->entier()),
                Cell::fromValue($ligne['litres_servis'], $this->litres()),
                Cell::fromValue($ligne['montant'], $this->francs()),
                Cell::fromValue(implode(', ', $ligne['vehicules'])),
                Cell::fromValue($ligne['nombre_anomalies'], $this->entier()),
            ]));
        }

        $this->filtre($feuille, 7, $d['par_chauffeur']->count());
    }

    // -----------------------------------------------------------------------
    // Confort de lecture
    // -----------------------------------------------------------------------

    /**
     * Fige la première ligne.
     *
     * Sans cela, l'intitulé des colonnes disparaît dès qu'on descend, et sur
     * une feuille de seize colonnes on ne sait plus ce qu'on lit.
     */
    private function figerEntete(Sheet $feuille): void
    {
        // « freezeRow » désigne la première ligne mobile, pas la dernière figée :
        // à 1, openspout n'écrit aucun volet.
        $feuille->setSheetView(new SheetView(freezeRow: 2));
    }

    /** Le filtre d'Excel, posé sur l'en-tête et les lignes de données. */
    private function filtre(Sheet $feuille, int $colonnes, int $lignes): void
    {
        if ($lignes < 1) {
            return;
        }

        $feuille->setAutoFilter(new AutoFilter(1, 1, $colonnes, $lignes + 1));
    }
}
