<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ModeSuivi;
use App\Models\Vehicule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Référentiel « Véhicules et engins » (§3). */
class VehiculeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehicule = $this->route('vehicule');
        $id = $vehicule instanceof Vehicule ? $vehicule->id : null;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('vehicules', 'code')->ignore($id)],
            'designation' => ['required', 'string', 'max:255'],
            'carburant_id' => ['required', 'integer', 'exists:carburants,id'],
            'mode_suivi' => ['required', Rule::enum(ModeSuivi::class)],
            'capacite_reservoir' => ['required', 'numeric', 'gt:0'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'code interne',
            'carburant_id' => 'carburant',
            'mode_suivi' => 'mode de suivi',
            'capacite_reservoir' => 'capacité du réservoir',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code interne est déjà attribué à un autre véhicule.',
            'capacite_reservoir.gt' => 'La capacité du réservoir doit être supérieure à zéro.',
        ];
    }
}
