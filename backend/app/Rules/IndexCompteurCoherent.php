<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Sortie;
use App\Models\Vehicule;
use App\Services\ConsommationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Contrôle obligatoire n°1 (§5 du cahier des charges) :
 * « Index compteur inférieur au précédent → saisie refusée ».
 *
 * Le contrôle est appliqué dans les deux sens. En création, seul le plein
 * précédent existe. En modification, remonter l'index au-dessus de celui du
 * plein suivant produirait exactement le même compteur qui recule, vu depuis
 * le plein d'après : la chaîne doit rester croissante de bout en bout.
 */
class IndexCompteurCoherent implements ValidationRule
{
    public function __construct(
        private readonly ?Vehicule $vehicule,
        private readonly ?string $date,
        private readonly ?int $sortieId,
        private readonly ConsommationService $consommation,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->vehicule === null || $this->date === null || ! is_numeric($value)) {
            return;
        }

        $index = (float) $value;
        $unite = $this->vehicule->mode_suivi->uniteIndex();

        $precedente = $this->consommation->sortiePrecedente(
            $this->vehicule->id,
            $this->date,
            $this->sortieId,
        );

        if ($precedente !== null && $index < $precedente->index_compteur) {
            $fail($this->message('inférieur au dernier index relevé', $index, $precedente, $unite));

            return;
        }

        $suivante = $this->consommation->sortieSuivante(
            $this->vehicule->id,
            $this->date,
            $this->sortieId,
        );

        if ($suivante !== null && $index > $suivante->index_compteur) {
            $fail($this->message('supérieur à l\'index du plein suivant', $index, $suivante, $unite));
        }
    }

    private function message(string $probleme, float $index, Sortie $voisine, string $unite): string
    {
        return sprintf(
            'Saisie refusée : l\'index %s %s est %s (%s %s le %s).',
            number_format($index, 2, ',', ' '),
            $unite,
            $probleme,
            number_format($voisine->index_compteur, 2, ',', ' '),
            $unite,
            // L'heure compte maintenant : deux pleins du même jour se
            // distinguent par elle, et un message qui ne donnerait que la date
            // laisserait le pompiste sans repère.
            $voisine->date_sortie->format('d/m/Y à H\hi'),
        );
    }
}
