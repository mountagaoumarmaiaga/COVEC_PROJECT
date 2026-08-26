<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Entree;
use Illuminate\Foundation\Http\FormRequest;

/** Saisie d'un remplissage de cuve — écran 1 « Entrées » (§2). */
class EntreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Même principe que pour les sorties : l'instant de la réception est posé
     * par le serveur à la création, et reste corrigeable en modification.
     */
    protected function prepareForValidation(): void
    {
        $entree = $this->route('entree');

        if ($entree instanceof Entree) {
            if (! $this->filled('date_entree')) {
                $this->merge(['date_entree' => $entree->date_entree->format('Y-m-d H:i:s')]);
            }

            return;
        }

        $this->merge(['date_entree' => now()->format('Y-m-d H:i:s')]);
    }

    public function rules(): array
    {
        return [
            'date_entree' => ['required', 'date'],
            'carburant_id' => ['required', 'integer', 'exists:carburants,id'],
            'fournisseur' => ['required', 'string', 'max:255'],
            'quantite_litres' => ['required', 'numeric', 'gt:0'],
            'prix_unitaire' => ['required', 'numeric', 'min:0'],
            'reference_bon' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'date_entree' => 'date',
            'carburant_id' => 'carburant',
            'quantite_litres' => 'quantité en litres',
            'prix_unitaire' => 'prix unitaire',
            'reference_bon' => 'référence du bon',
        ];
    }

    public function messages(): array
    {
        return [
            'quantite_litres.gt' => 'La quantité livrée doit être supérieure à zéro.',
            'carburant_id.exists' => 'Ce carburant n\'existe pas dans le référentiel.',
        ];
    }
}
