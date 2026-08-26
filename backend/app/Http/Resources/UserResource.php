<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'matricule' => $this->matricule,
            'role' => $this->role->value,
            'role_libelle' => $this->role->libelle(),
            'role_description' => $this->role->description(),
            'actif' => $this->actif,

            // Les droits voyagent avec le compte : l'interface masque ce qui
            // est interdit sans réimplémenter la matrice des rôles, et sans
            // risquer de diverger du serveur qui, lui, fait foi.
            'peut_servir' => $this->peutServir(),
            'peut_gerer' => $this->peutGerer(),
        ];
    }
}
