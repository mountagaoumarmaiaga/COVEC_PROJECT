<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Chauffeur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Référentiel « Chauffeurs » (§3) : nom, matricule. */
class ChauffeurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $chauffeur = $this->route('chauffeur');
        $id = $chauffeur instanceof Chauffeur ? $chauffeur->id : null;

        return [
            'nom' => ['required', 'string', 'max:255'],
            'matricule' => ['required', 'string', 'max:50', Rule::unique('chauffeurs', 'matricule')->ignore($id)],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'matricule.unique' => 'Ce matricule est déjà attribué à un autre chauffeur.',
        ];
    }
}
