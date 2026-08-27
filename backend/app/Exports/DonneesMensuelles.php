<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Entree;
use App\Models\Sortie;
use App\Services\StockService;
use Illuminate\Support\Collection;

/**
 * Tout ce que contient le rapport d'un mois, assemblé une fois.
 *
 * Le classeur et le PDF présentent les mêmes chiffres différemment. Les
 * calculer chacun de son côté finirait par les faire diverger — une correction
 * appliquée à l'un et oubliée dans l'autre suffit, et le jour où les deux
 * documents ne concordent plus, c'est le registre entier qu'on cesse de
 * croire. Ils partagent donc cette source.
 */
class DonneesMensuelles
{
    private const MOIS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    public function __construct(private readonly StockService $stock) {}

    public function periode(int $annee, int $mois): string
    {
        return sprintf('%s %d', self::MOIS[$mois] ?? (string) $mois, $annee);
    }

    /**
     * La période précédée de sa préposition, élision comprise.
     *
     * « Mouvements de août » se remarque sur un document qu'on transmet.
     * Avril, août et octobre commencent par une voyelle et demandent « d' ».
     */
    public function deLaPeriode(int $annee, int $mois): string
    {
        $periode = $this->periode($annee, $mois);
        $initiale = mb_strtolower(mb_substr($periode, 0, 1));

        return (in_array($initiale, ['a', 'e', 'i', 'o', 'u', 'é', 'â'], true) ? "d'" : 'de ').$periode;
    }

    /**
     * @return array{
     *     annee: int, mois: int, periode: string, de_la_periode: string, editee_le: string,
     *     synthese: array, totaux: array,
     *     entrees: Collection, sorties: Collection,
     *     par_vehicule: Collection, par_chauffeur: Collection,
     *     totaux_entrees: array, totaux_sorties: array,
     * }
     */
    public function pour(int $annee, int $mois): array
    {
        $entrees = $this->entrees($annee, $mois);
        $sorties = $this->sorties($annee, $mois);

        return [
            'annee' => $annee,
            'mois' => $mois,
            'periode' => $this->periode($annee, $mois),
            'de_la_periode' => $this->deLaPeriode($annee, $mois),
            'editee_le' => now()->format('d/m/Y à H:i'),

            'synthese' => $this->stock->synthese(),
            'totaux' => $this->stock->totauxMois($annee, $mois),

            'entrees' => $entrees,
            'sorties' => $sorties,

            'par_vehicule' => $this->stock->consommationParVehicule($annee, $mois),
            'par_chauffeur' => $this->stock->consommationParChauffeur($annee, $mois),

            // Les totaux de bas de tableau, calculés ici pour que les deux
            // documents n'aient pas à les refaire.
            'totaux_entrees' => [
                'nombre' => $entrees->count(),
                'litres' => round((float) $entrees->sum('quantite_litres'), 2),
                'montant' => round((float) $entrees->sum('montant'), 2),
            ],
            'totaux_sorties' => [
                'nombre' => $sorties->count(),
                'litres' => round((float) $sorties->sum('litres_servis'), 2),
                'montant' => round((float) $sorties->sum('montant'), 2),
                'anomalies' => $sorties->where('anomalie', true)->count(),
            ],
        ];
    }

    /** @return Collection<int, Entree> */
    private function entrees(int $annee, int $mois): Collection
    {
        return Entree::query()
            ->with('carburant')
            ->duMois($annee, $mois)
            ->orderBy('date_entree')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Sortie> */
    private function sorties(int $annee, int $mois): Collection
    {
        return Sortie::query()
            ->with(['vehicule.carburant', 'chauffeur'])
            ->duMois($annee, $mois)
            ->chronologique()
            ->get();
    }
}
