<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Vehicule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Contrôle obligatoire n°2 (§5 du cahier des charges) :
 * « Litres servis supérieurs à la capacité du réservoir → saisie refusée ».
 *
 * Un réservoir ne peut pas absorber plus que son volume en une seule fois.
 * Au-delà, c'est une erreur de frappe ou un détournement, jamais un plein.
 */
class LitresDansCapaciteReservoir implements ValidationRule
{
    public function __construct(private readonly ?Vehicule $vehicule) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->vehicule === null || ! is_numeric($value)) {
            return;
        }

        $litres = (float) $value;
        $capacite = $this->vehicule->capacite_reservoir;

        if ($litres > $capacite) {
            $fail(sprintf(
                'Saisie refusée : %s L dépassent la capacité du réservoir de %s (%s L).',
                number_format($litres, 2, ',', ' '),
                $this->vehicule->designation,
                number_format($capacite, 2, ',', ' '),
            ));
        }
    }
}
