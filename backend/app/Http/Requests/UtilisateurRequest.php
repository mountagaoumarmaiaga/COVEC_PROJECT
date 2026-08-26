<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création et modification d'un compte, réservées au gestionnaire. */
class UtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $utilisateur = $this->route('utilisateur');
        $id = $utilisateur instanceof User ? $utilisateur->id : null;

        return [
            'nom' => ['required', 'string', 'max:255'],
            'matricule' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'matricule')->ignore($id),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            // Obligatoire à la création, facultatif ensuite : laisser le champ
            // vide en modification conserve le mot de passe existant.
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:8'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nom' => 'nom',
            'matricule' => 'matricule',
            'role' => 'rôle',
            'password' => 'mot de passe',
        ];
    }

    public function messages(): array
    {
        return [
            'matricule.unique' => 'Ce matricule est déjà attribué à un autre compte.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ];
    }
}
