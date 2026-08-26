<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Référentiel « Carburants et cuves » (§3).
 *
 * Un carburant et sa cuve se modifient d'un seul geste : ils forment une même
 * ligne à l'écran, et séparer les deux obligerait à enregistrer deux fois.
 */
class CarburantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:255'],
            'prix_par_defaut' => ['required', 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
            'cuve' => ['required', 'array'],
            'cuve.nom' => ['required', 'string', 'max:255'],
            'cuve.capacite' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'libelle' => 'libellé',
            'prix_par_defaut' => 'prix par défaut',
            'cuve.nom' => 'nom de la cuve',
            'cuve.capacite' => 'capacité de la cuve',
        ];
    }

    public function messages(): array
    {
        return [
            'cuve.capacite.gt' => 'La capacité de la cuve doit être supérieure à zéro.',
        ];
    }
}
