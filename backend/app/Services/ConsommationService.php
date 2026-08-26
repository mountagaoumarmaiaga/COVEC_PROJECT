<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModeSuivi;
use App\Models\Sortie;
use App\Models\Vehicule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Calcul de la consommation d'un plein et détection des pleins anormaux.
 *
 * Méthode retenue : le « plein complet ». Les litres servis lors d'un plein
 * remplacent ce qui a été consommé depuis le plein précédent. La consommation
 * d'un plein se rapporte donc à la distance parcourue depuis le plein d'avant,
 * ce qui implique qu'un tout premier plein ne sert que de repère.
 *
 * Depuis que les sorties sont horodatées à la seconde, l'ordre de la chaîne
 * suit directement l'instant du plein. L'identifiant ne départage plus que
 * deux pleins enregistrés au même instant.
 */
class ConsommationService
{
    /**
     * Seuil du §5 du cahier des charges : au-delà de +30 % par rapport à la
     * moyenne du véhicule, le plein est signalé en rouge.
     */
    public const SEUIL_ANOMALIE = 0.30;

    private function instant(Carbon|string $date): string
    {
        return ($date instanceof Carbon ? $date : Carbon::parse($date))->format('Y-m-d H:i:s');
    }

    /**
     * Sortie qui précède immédiatement une position donnée dans la chaîne
     * d'un véhicule.
     *
     * @param  int|null  $sortieId  Identifiant de la sortie concernée, ou null
     *                              s'il s'agit d'une saisie encore non
     *                              enregistrée : elle se place alors après
     *                              celles du même instant.
     */
    public function sortiePrecedente(
        int $vehiculeId,
        Carbon|string $date,
        ?int $sortieId = null,
    ): ?Sortie {
        $instant = $this->instant($date);

        return Sortie::query()
            ->where('vehicule_id', $vehiculeId)
            ->where(function (Builder $q) use ($instant, $sortieId) {
                $q->where('date_sortie', '<', $instant);

                $q->orWhere(function (Builder $memeInstant) use ($instant, $sortieId) {
                    $memeInstant->where('date_sortie', '=', $instant);

                    if ($sortieId !== null) {
                        $memeInstant->where('id', '<', $sortieId);
                    }
                });
            })
            ->orderByDesc('date_sortie')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Sortie qui suit immédiatement une position donnée dans la chaîne.
     *
     * Utilisée en modification : relever l'index d'un plein au-dessus de celui
     * du plein suivant casserait la progression du compteur aussi sûrement que
     * de le passer sous celui du plein précédent.
     */
    public function sortieSuivante(
        int $vehiculeId,
        Carbon|string $date,
        ?int $sortieId = null,
    ): ?Sortie {
        $instant = $this->instant($date);

        return Sortie::query()
            ->where('vehicule_id', $vehiculeId)
            ->where(function (Builder $q) use ($instant, $sortieId) {
                $q->where('date_sortie', '>', $instant);

                $q->orWhere(function (Builder $memeInstant) use ($instant, $sortieId) {
                    $memeInstant->where('date_sortie', '=', $instant);

                    if ($sortieId !== null) {
                        $memeInstant->where('id', '>', $sortieId);
                    } else {
                        // Une saisie non enregistrée se place en dernier :
                        // rien du même instant ne la suit.
                        $memeInstant->whereRaw('1 = 0');
                    }
                });
            })
            ->orderBy('date_sortie')
            ->orderBy('id')
            ->first();
    }

    /**
     * Consommation d'un plein, dans l'unité correspondant au mode de suivi :
     * L/100 km pour les véhicules roulants, L/h pour les engins.
     */
    public function consommationPour(ModeSuivi $mode, float $litres, float $distance): float
    {
        return match ($mode) {
            ModeSuivi::Kilometrage => round($litres / $distance * 100, 3),
            ModeSuivi::Heures => round($litres / $distance, 3),
        };
    }

    /**
     * Renseigne les colonnes calculées d'une sortie sans l'enregistrer.
     *
     * Toutes les valeurs sont d'abord remises à zéro : une sortie recalculée
     * après modification d'un plein antérieur ne doit pas conserver le
     * résultat de son calcul précédent.
     */
    public function calculer(Sortie $sortie): Sortie
    {
        $vehicule = $sortie->relationLoaded('vehicule')
            ? $sortie->vehicule
            : $sortie->vehicule()->first();

        $sortie->distance_parcourue = null;
        $sortie->consommation = null;
        $sortie->moyenne_reference = null;
        $sortie->ecart_pourcentage = null;
        $sortie->anomalie = false;

        $precedente = $this->sortiePrecedente(
            $sortie->vehicule_id,
            $sortie->date_sortie,
            $sortie->id,
        );

        // Premier plein du véhicule : il n'existe aucun index de départ, donc
        // aucune distance et aucune consommation ne peuvent être établies.
        if ($precedente === null) {
            return $sortie;
        }

        $distance = round($sortie->index_compteur - $precedente->index_compteur, 2);
        $sortie->distance_parcourue = $distance;

        // Index identique au précédent : le véhicule n'a pas tourné entre les
        // deux pleins. La consommation serait une division par zéro.
        if ($distance <= 0) {
            return $sortie;
        }

        $sortie->consommation = $this->consommationPour(
            $vehicule->mode_suivi,
            $sortie->litres_servis,
            $distance,
        );

        $moyenne = $this->moyenneAvant($sortie, $vehicule);

        // Deuxième plein du véhicule : une consommation existe, mais il n'y a
        // encore rien à quoi la comparer.
        if ($moyenne === null || $moyenne <= 0.0) {
            return $sortie;
        }

        $sortie->moyenne_reference = $moyenne;
        $sortie->ecart_pourcentage = round(($sortie->consommation - $moyenne) / $moyenne * 100, 2);
        $sortie->anomalie = $sortie->consommation > $moyenne * (1 + self::SEUIL_ANOMALIE);

        return $sortie;
    }

    /**
     * Moyenne du véhicule sur les pleins antérieurs à celui-ci.
     *
     * Moyenne pondérée — litres cumulés rapportés à la distance cumulée —
     * et non moyenne des consommations individuelles : un plein pris après
     * un trajet de 20 km ne doit pas peser autant qu'un plein pris après 800 km.
     */
    public function moyenneAvant(Sortie $sortie, ?Vehicule $vehicule = null): ?float
    {
        $vehicule ??= $sortie->vehicule()->first();
        $instant = $this->instant($sortie->date_sortie);

        $anterieures = Sortie::query()
            ->where('vehicule_id', $sortie->vehicule_id)
            ->whereNotNull('consommation')
            ->where(function (Builder $q) use ($instant, $sortie) {
                $q->where('date_sortie', '<', $instant);

                $q->orWhere(function (Builder $memeInstant) use ($instant, $sortie) {
                    $memeInstant->where('date_sortie', '=', $instant);

                    if ($sortie->id !== null) {
                        $memeInstant->where('id', '<', $sortie->id);
                    }
                });
            })
            ->get(['litres_servis', 'distance_parcourue']);

        $distance = (float) $anterieures->sum('distance_parcourue');

        if ($distance <= 0.0) {
            return null;
        }

        return $this->consommationPour(
            $vehicule->mode_suivi,
            (float) $anterieures->sum('litres_servis'),
            $distance,
        );
    }

    /**
     * Recalcule toute la chaîne d'un véhicule, du plus ancien plein au plus récent.
     *
     * Nécessaire dès qu'un plein est modifié ou supprimé : la consommation d'un
     * plein dépend de l'index du précédent, et la moyenne de référence de tous
     * ceux d'avant. Toucher un maillon invalide donc tous les suivants.
     */
    public function recalculerChaine(Vehicule $vehicule): void
    {
        foreach ($vehicule->sortiesChronologiques()->get() as $sortie) {
            $sortie->setRelation('vehicule', $vehicule);
            $this->calculer($sortie);
            $sortie->saveQuietly();
        }
    }
}
