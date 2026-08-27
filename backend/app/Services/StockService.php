<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Carburant;
use App\Models\Chauffeur;
use App\Models\Cuve;
use App\Models\Entree;
use App\Models\Sortie;
use App\Models\Vehicule;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Écran 3 « Stock et consommation » (§2) et résultats attendus (§4).
 *
 * Tout se calcule par carburant. Additionner des litres de gasoil et
 * d'essence donnerait un nombre qui ne correspond à aucun réservoir réel :
 * chaque carburant a sa cuve, son stock et son prix moyen.
 *
 * Le stock n'est jamais stocké dans une colonne : il est recalculé à la
 * demande comme la différence entre les entrées et les sorties. Un compteur
 * entretenu à la main finirait par diverger de l'historique des mouvements,
 * et c'est l'historique qui fait foi.
 *
 * Les totaux sont obtenus par des agrégats groupés, calculés une fois et
 * gardés le temps de la requête HTTP. La version précédente interrogeait la
 * base carburant par carburant et refaisait les mêmes sommes plusieurs fois
 * par écran : indolore sur une base locale, mais l'écran de stock demandait
 * dix secondes une fois la base passée sur un serveur distant, où chaque
 * aller-retour coûte quelques centaines de millisecondes.
 */
class StockService
{
    /** @var Collection<int, Carburant>|null */
    private ?Collection $carburants = null;

    /** @var array<int, array{litres: float, montant: float}>|null */
    private ?array $entrees = null;

    /** @var array<int, array{litres: float, montant: float, anomalies: int}>|null */
    private ?array $sorties = null;

    public function __construct(private readonly ConsommationService $consommation) {}

    /** @return Collection<int, Carburant> */
    public function carburants(): Collection
    {
        return $this->carburants ??= Carburant::query()->with('cuve')->orderBy('libelle')->get();
    }

    /**
     * Litres et montants reçus, par carburant, en une seule requête.
     *
     * L'agrégat s'appelle « somme_montant » et non « montant » : le modèle
     * Entree possède un accesseur de ce nom. Un alias SQL homonyme est masqué
     * par l'accesseur, qui multiplie alors des colonnes absentes de la requête
     * groupée et renvoie zéro — sans la moindre erreur.
     *
     * @return array<int, array{litres: float, montant: float}>
     */
    private function entreesParCarburant(): array
    {
        return $this->entrees ??= Entree::query()
            ->selectRaw('carburant_id, SUM(quantite_litres) as litres, SUM(quantite_litres * prix_unitaire) as somme_montant')
            ->groupBy('carburant_id')
            ->get()
            ->mapWithKeys(fn ($l) => [(int) $l->carburant_id => [
                'litres' => (float) $l->litres,
                'montant' => (float) $l->somme_montant,
            ]])
            ->all();
    }

    /**
     * Litres servis, montants et pleins signalés, par carburant.
     *
     * Le carburant d'une sortie se déduit de son véhicule : la jointure fait
     * ici ce que la relation ferait une requête à la fois.
     *
     * @return array<int, array{litres: float, montant: float, anomalies: int}>
     */
    private function sortiesParCarburant(): array
    {
        return $this->sorties ??= Sortie::query()
            ->join('vehicules', 'vehicules.id', '=', 'sorties.vehicule_id')
            ->selectRaw('vehicules.carburant_id as carburant_id')
            ->selectRaw('SUM(sorties.litres_servis) as litres')
            ->selectRaw('SUM(sorties.litres_servis * sorties.prix_unitaire) as somme_montant')
            ->selectRaw('SUM(CASE WHEN sorties.anomalie THEN 1 ELSE 0 END) as anomalies')
            ->groupBy('vehicules.carburant_id')
            ->get()
            ->mapWithKeys(fn ($l) => [(int) $l->carburant_id => [
                'litres' => (float) $l->litres,
                'montant' => (float) $l->somme_montant,
                'anomalies' => (int) $l->anomalies,
            ]])
            ->all();
    }

    public function totalEntrees(Carburant $carburant): float
    {
        return $this->entreesParCarburant()[$carburant->id]['litres'] ?? 0.0;
    }

    public function totalSorties(Carburant $carburant): float
    {
        return $this->sortiesParCarburant()[$carburant->id]['litres'] ?? 0.0;
    }

    /** Litres restants dans la cuve de ce carburant : entrées moins sorties (§4). */
    public function stockActuel(Carburant $carburant): float
    {
        return round($this->totalEntrees($carburant) - $this->totalSorties($carburant), 2);
    }

    /**
     * Coût unitaire moyen pondéré des achats de ce carburant.
     *
     * Indique ce que vaut le stock en cuve. Les sorties, elles, portent chacune
     * le prix en vigueur au moment du plein : elles ne sont pas valorisées à
     * cette moyenne.
     */
    public function prixMoyenPondere(Carburant $carburant): float
    {
        $litres = $this->totalEntrees($carburant);

        if ($litres <= 0.0) {
            return $carburant->prix_par_defaut;
        }

        return round(($this->entreesParCarburant()[$carburant->id]['montant'] ?? 0.0) / $litres, 4);
    }

    /** Bandeau d'un carburant sur l'écran « Stock et consommation ». */
    public function bilan(Carburant $carburant): array
    {
        $cuve = $carburant->cuve ?? Cuve::pour($carburant);
        $stock = $this->stockActuel($carburant);

        return [
            'carburant' => $this->identite($carburant),
            'cuve' => [
                'id' => $cuve->id,
                'nom' => $cuve->nom,
                'capacite' => $cuve->capacite,
            ],
            'stock_actuel' => $stock,
            'total_entrees' => round($this->totalEntrees($carburant), 2),
            'total_sorties' => round($this->totalSorties($carburant), 2),
            'prix_moyen_pondere' => $this->prixMoyenPondere($carburant),
            'taux_remplissage' => $cuve->capacite > 0
                ? round($stock / $cuve->capacite * 100, 1)
                : null,
            'nombre_pleins_anormaux' => $this->sortiesParCarburant()[$carburant->id]['anomalies'] ?? 0,
        ];
    }

    public function synthese(): array
    {
        $bilans = $this->carburants()->map(fn (Carburant $c) => $this->bilan($c))->values();

        return [
            'carburants' => $bilans,
            'nombre_pleins_anormaux' => (int) $bilans->sum('nombre_pleins_anormaux'),
        ];
    }

    /**
     * Totaux du mois (§4), par carburant puis toutes cuves confondues.
     *
     * Les litres ne s'additionnent qu'à l'intérieur d'un carburant ; les
     * montants, eux, s'additionnent sans difficulté — un franc reste un franc.
     */
    public function totauxMois(int $annee, int $mois): array
    {
        $entrees = Entree::query()
            ->duMois($annee, $mois)
            ->selectRaw('carburant_id, COUNT(*) as nombre, SUM(quantite_litres) as litres, SUM(quantite_litres * prix_unitaire) as somme_montant')
            ->groupBy('carburant_id')
            ->get()
            ->keyBy('carburant_id');

        $sorties = Sortie::query()
            ->join('vehicules', 'vehicules.id', '=', 'sorties.vehicule_id')
            ->duMois($annee, $mois)
            ->selectRaw('vehicules.carburant_id as carburant_id')
            ->selectRaw('COUNT(*) as nombre')
            ->selectRaw('SUM(sorties.litres_servis) as litres')
            ->selectRaw('SUM(sorties.litres_servis * sorties.prix_unitaire) as somme_montant')
            ->selectRaw('SUM(CASE WHEN sorties.anomalie THEN 1 ELSE 0 END) as anomalies')
            ->groupBy('vehicules.carburant_id')
            ->get()
            ->keyBy('carburant_id');

        $parCarburant = $this->carburants()->map(function (Carburant $carburant) use ($entrees, $sorties) {
            $e = $entrees->get($carburant->id);
            $s = $sorties->get($carburant->id);

            return [
                'carburant' => $this->identite($carburant),
                'entrees' => [
                    'nombre' => (int) ($e->nombre ?? 0),
                    'litres' => round((float) ($e->litres ?? 0), 2),
                    'montant' => round((float) ($e->somme_montant ?? 0), 2),
                ],
                'sorties' => [
                    'nombre' => (int) ($s->nombre ?? 0),
                    'litres' => round((float) ($s->litres ?? 0), 2),
                    // Somme des montants réellement enregistrés, et non une
                    // estimation au prix moyen : chaque plein porte le prix
                    // du litre en vigueur au moment où il a été servi.
                    'montant' => round((float) ($s->somme_montant ?? 0), 2),
                    'nombre_anomalies' => (int) ($s->anomalies ?? 0),
                ],
            ];
        })->values();

        return [
            'annee' => $annee,
            'mois' => $mois,
            'carburants' => $parCarburant,
            'ensemble' => [
                'entrees' => [
                    'nombre' => (int) $parCarburant->sum(fn ($c) => $c['entrees']['nombre']),
                    'montant' => round((float) $parCarburant->sum(fn ($c) => $c['entrees']['montant']), 2),
                ],
                'sorties' => [
                    'nombre' => (int) $parCarburant->sum(fn ($c) => $c['sorties']['nombre']),
                    'montant' => round((float) $parCarburant->sum(fn ($c) => $c['sorties']['montant']), 2),
                    'nombre_anomalies' => (int) $parCarburant->sum(fn ($c) => $c['sorties']['nombre_anomalies']),
                ],
            ],
        ];
    }

    /**
     * Consommation par véhicule (§2, écran 3).
     *
     * @param  int|null  $annee  Restreint au mois indiqué si année et mois sont fournis.
     */
    public function consommationParVehicule(?int $annee = null, ?int $mois = null): Collection
    {
        $periode = $annee !== null && $mois !== null;

        return Vehicule::query()
            ->with(['carburant', 'sorties' => function (HasMany $q) use ($periode, $annee, $mois) {
                if ($periode) {
                    $q->duMois($annee, $mois);
                }
            }])
            ->orderBy('code')
            ->get()
            ->map(function (Vehicule $vehicule) {
                $sorties = $vehicule->sorties;

                // Le premier plein d'un véhicule, et tout plein pris sans que
                // l'index ait bougé, n'ont pas de consommation exploitable.
                // Ils comptent dans les litres servis mais pas dans la moyenne.
                $calculables = $sorties->whereNotNull('consommation');
                $distance = round((float) $calculables->sum('distance_parcourue'), 2);

                return [
                    'vehicule' => [
                        'id' => $vehicule->id,
                        'code' => $vehicule->code,
                        'designation' => $vehicule->designation,
                        'mode_suivi' => $vehicule->mode_suivi->value,
                        'unite_consommation' => $vehicule->mode_suivi->uniteConsommation(),
                        'unite_index' => $vehicule->mode_suivi->uniteIndex(),
                        'carburant' => $vehicule->carburant
                            ? $this->identite($vehicule->carburant)
                            : null,
                    ],
                    'nombre_pleins' => $sorties->count(),
                    'litres_servis' => round((float) $sorties->sum('litres_servis'), 2),
                    'montant' => round((float) $sorties->sum('montant'), 2),
                    'distance_totale' => $distance,
                    'moyenne_consommation' => $distance > 0
                        ? $this->consommation->consommationPour(
                            $vehicule->mode_suivi,
                            (float) $calculables->sum('litres_servis'),
                            $distance,
                        )
                        : null,
                    'dernier_index' => $sorties->max('index_compteur'),
                    'nombre_anomalies' => $sorties->where('anomalie', true)->count(),
                ];
            })
            ->values();
    }

    /**
     * Activité par chauffeur sur la période (§4).
     *
     * Le registre sert aussi à répondre à « qui a pris combien » : sans cette
     * vue, il faudrait dépouiller le journal des pleins à la main.
     *
     * @param  int|null  $annee  Restreint au mois indiqué si année et mois sont fournis.
     */
    public function consommationParChauffeur(?int $annee = null, ?int $mois = null): Collection
    {
        $periode = $annee !== null && $mois !== null;

        return Chauffeur::query()
            ->with(['sorties' => function (HasMany $q) use ($periode, $annee, $mois) {
                $q->with('vehicule.carburant');

                if ($periode) {
                    $q->duMois($annee, $mois);
                }
            }])
            ->orderBy('nom')
            ->get()
            ->map(function (Chauffeur $chauffeur) {
                $sorties = $chauffeur->sorties;

                return [
                    'chauffeur' => [
                        'id' => $chauffeur->id,
                        'nom' => $chauffeur->nom,
                        'matricule' => $chauffeur->matricule,
                    ],
                    'nombre_pleins' => $sorties->count(),
                    'litres_servis' => round((float) $sorties->sum('litres_servis'), 2),
                    'montant' => round((float) $sorties->sum('montant'), 2),
                    // Un chauffeur peut conduire plusieurs véhicules dans le mois.
                    'vehicules' => $sorties->pluck('vehicule.code')->unique()->sort()->values()->all(),
                    'nombre_anomalies' => $sorties->where('anomalie', true)->count(),
                ];
            })
            // Un chauffeur qui n'a rien pris ce mois-ci alourdirait le tableau
            // sans rien apprendre.
            ->filter(fn (array $l) => $l['nombre_pleins'] > 0)
            ->values();
    }

    /** @return array{id: int, code: string, libelle: string, prix_par_defaut: float} */
    private function identite(Carburant $carburant): array
    {
        return [
            'id' => $carburant->id,
            'code' => $carburant->code,
            'libelle' => $carburant->libelle,
            'prix_par_defaut' => $carburant->prix_par_defaut,
        ];
    }
}
